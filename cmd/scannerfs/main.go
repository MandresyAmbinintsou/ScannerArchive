package main

import (
	"bufio"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"sort"
	"strings"
	"sync"
	"time"
)

type ScanSummary struct {
	Ok            bool   `json:"ok"`
	Root          string `json:"root"`
	Platform      string `json:"platform"`
	GoVersion     string `json:"go_version"`
	StartedAtUTC  string `json:"started_at_utc"`
	FinishedAtUTC string `json:"finished_at_utc"`
	DurationMs    int64  `json:"duration_ms"`
	Matricules    int    `json:"matricules"`
	SousDossiers  int    `json:"sous_dossiers"`
	Images        int    `json:"images"`
	Warnings      int    `json:"warnings"`
	Error         string `json:"error,omitempty"`
}

type Matricule struct {
	Name         string `json:"name"`
	Path         string `json:"path"`
	SousCount    int    `json:"sous_count"`
	ImagesCount  int    `json:"images_count"`
	SkippedItems int    `json:"skipped_items"`
}

type SousDossier struct {
	MatriculeName string `json:"matricule_name"`
	Name          string `json:"name"`
	Path          string `json:"path"`
	ImagesCount   int    `json:"images_count"`
	SkippedItems  int    `json:"skipped_items"`
}

type ImageItem struct {
	MatriculeName string `json:"matricule_name"`
	SousName      string `json:"sous_name"`
	FileName      string `json:"file_name"`
	FullPath      string `json:"full_path"`
}

type Event struct {
	Type    string       `json:"type"`
	Summary *ScanSummary `json:"summary,omitempty"`
	Warn    string       `json:"warn,omitempty"`
	Mat     *Matricule   `json:"matricule,omitempty"`
	Sous    *SousDossier `json:"sousdossier,omitempty"`
	Image   *ImageItem   `json:"image,omitempty"`
}

func main() {
	rootFlag := flag.String("root", "", "Dossier racine à scanner")
	workers := flag.Int("workers", max(4, runtime.NumCPU()), "Nombre de workers (par défaut: CPU)")
	outPath := flag.String("out", "", "Fichier de sortie JSONL (sinon stdout)")
	emitImages := flag.Bool("emit-images", false, "Émettre chaque image (peut générer beaucoup de lignes)")
	flag.Parse()

	root := strings.TrimSpace(*rootFlag)
	if root == "" {
		fmt.Fprintln(os.Stderr, "Erreur: --root est obligatoire")
		os.Exit(2)
	}

	rootReal, err := filepath.Abs(root)
	if err == nil {
		root = rootReal
	}
	if st, err := os.Stat(root); err != nil || !st.IsDir() {
		fmt.Fprintln(os.Stderr, "Erreur: dossier invalide:", root)
		os.Exit(2)
	}

	var out io.Writer = os.Stdout
	var file *os.File
	if *outPath != "" {
		file, err = os.Create(*outPath)
		if err != nil {
			fmt.Fprintln(os.Stderr, "Erreur: impossible de créer le fichier:", err)
			os.Exit(2)
		}
		defer file.Close()
		out = file
	}
	bw := bufio.NewWriterSize(out, 1<<20)
	defer bw.Flush()

	start := time.Now().UTC()
	summary := &ScanSummary{
		Ok:           true,
		Root:         root,
		Platform:     runtime.GOOS + "/" + runtime.GOARCH,
		GoVersion:    runtime.Version(),
		StartedAtUTC: start.Format(time.RFC3339Nano),
	}

	enc := json.NewEncoder(bw)
	enc.SetEscapeHTML(false)

	warn := func(msg string) {
		summary.Warnings++
		_ = enc.Encode(Event{Type: "warn", Warn: msg})
	}

	entries, err := os.ReadDir(root)
	if err != nil {
		summary.Ok = false
		summary.Error = err.Error()
		summary.FinishedAtUTC = time.Now().UTC().Format(time.RFC3339Nano)
		summary.DurationMs = time.Since(start).Milliseconds()
		_ = enc.Encode(Event{Type: "summary", Summary: summary})
		os.Exit(1)
	}

	// Liste des matricules (dossiers enfants du root).
	matNames := make([]string, 0, len(entries))
	for _, e := range entries {
		if e.IsDir() {
			matNames = append(matNames, e.Name())
		}
	}
	sort.Strings(matNames)

	type job struct {
		matName string
	}
	jobs := make(chan job)
	var wg sync.WaitGroup
	var mu sync.Mutex

	allowedExt := map[string]struct{}{
		".jpg": {}, ".jpeg": {}, ".png": {}, ".gif": {}, ".webp": {},
	}

	workerFn := func() {
		defer wg.Done()
		for j := range jobs {
			matPath := filepath.Join(root, j.matName)
			sousEntries, err := os.ReadDir(matPath)
			if err != nil {
				warn(fmt.Sprintf("Impossible de lire %s: %v", matPath, err))
				continue
			}

			// Collect sous-dossiers (dirs only)
			sousNames := make([]string, 0, len(sousEntries))
			for _, s := range sousEntries {
				if s.IsDir() {
					sousNames = append(sousNames, s.Name())
				}
			}
			sort.Strings(sousNames)

			localSous := len(sousNames)
			localImg := 0
			skippedMat := 0

			_ = enc.Encode(Event{Type: "matricule", Mat: &Matricule{
				Name:         j.matName,
				Path:         matPath,
				SousCount:    localSous,
				ImagesCount:  0,
				SkippedItems: 0,
			}})

			for _, sousName := range sousNames {
				sousPath := filepath.Join(matPath, sousName)
				imgEntries, err := os.ReadDir(sousPath)
				if err != nil {
					warn(fmt.Sprintf("Impossible de lire %s: %v", sousPath, err))
					skippedMat++
					continue
				}

				nbImages := 0
				skippedSous := 0
				var collectedImages []string
				for _, img := range imgEntries {
					if img.IsDir() {
						continue
					}
					ext := strings.ToLower(filepath.Ext(img.Name()))
					if _, ok := allowedExt[ext]; !ok {
						continue
					}
					nbImages++
					if *emitImages {
						collectedImages = append(collectedImages, img.Name())
					}
				}

				localImg += nbImages

				// Émettre d'abord le sous-dossier pour que l'ingesteur PHP ait son ID
				_ = enc.Encode(Event{Type: "sousdossier", Sous: &SousDossier{
					MatriculeName: j.matName,
					Name:          sousName,
					Path:          sousPath,
					ImagesCount:   nbImages,
					SkippedItems:  skippedSous,
				}})

				// Émettre les images seulement après le sous-dossier
				for _, imgName := range collectedImages {
					_ = enc.Encode(Event{Type: "image", Image: &ImageItem{
						MatriculeName: j.matName,
						SousName:      sousName,
						FileName:      imgName,
						FullPath:      filepath.Join(sousPath, imgName),
					}})
				}
			}

			_ = enc.Encode(Event{Type: "matricule_done", Mat: &Matricule{
				Name:         j.matName,
				Path:         matPath,
				SousCount:    localSous,
				ImagesCount:  localImg,
				SkippedItems: skippedMat,
			}})

			mu.Lock()
			summary.Matricules++
			summary.SousDossiers += localSous
			summary.Images += localImg
			mu.Unlock()
		}
	}

	w := *workers
	if w < 1 {
		w = 1
	}
	wg.Add(w)
	for i := 0; i < w; i++ {
		go workerFn()
	}

	for _, name := range matNames {
		jobs <- job{matName: name}
	}
	close(jobs)
	wg.Wait()

	summary.FinishedAtUTC = time.Now().UTC().Format(time.RFC3339Nano)
	summary.DurationMs = time.Since(start).Milliseconds()
	_ = enc.Encode(Event{Type: "summary", Summary: summary})
}

func max(a, b int) int {
	if a > b {
		return a
	}
	return b
}
