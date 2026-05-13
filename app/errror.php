<?php
// On envoie le code HTTP 404 pour que les moteurs de recherche comprennent l'erreur
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 404 - Oups !</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        coffee: {
                            light: '#a1887f',
                            DEFAULT: '#6d4c41',
                            dark: '#3e2723'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-screen overflow-hidden antialiased font-sans transition-colors duration-300">

<div class="relative h-full w-full bg-cover bg-center flex items-center justify-center p-6" 
     style="background-image: url('../public/img/erreur404_cafe.png');">
    
    <!-- Overlay for better readability if needed -->
    <div class="absolute inset-0 bg-slate-950/20 backdrop-blur-[2px]"></div>

    <div class="relative z-10 max-w-lg w-full rounded-3xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-white/20 dark:border-white/5 p-12 text-center shadow-2xl animate-in fade-in zoom-in duration-500">
        <div class="inline-flex h-20 w-20 items-center justify-center rounded-2xl bg-coffee text-white shadow-lg mb-8 rotate-3">
            <i class="fas fa-mug-hot text-3xl"></i>
        </div>
        
        <h1 class="text-5xl font-black text-slate-900 dark:text-white tracking-tighter uppercase italic mb-4">
            Erreur 404
        </h1>
        
        <p class="text-lg font-bold text-coffee-dark dark:text-coffee-light leading-relaxed mb-10">
            Oups ! Le café s'est renversé...<br>
            <span class="text-sm font-black uppercase tracking-widest opacity-60">et cette page aussi.</span>
        </p>
        
        <a href="/" class="inline-flex items-center gap-3 px-10 py-5 rounded-2xl bg-coffee text-white text-[11px] font-black uppercase tracking-widest shadow-lg shadow-coffee/20 hover:bg-coffee-dark hover:scale-[1.02] transition active:scale-95">
            <i class="fas fa-home"></i>
            Retour à l'accueil
        </a>
    </div>
</div>

<script>
    if (localStorage.theme === 'light' || (!('theme' in localStorage) && !window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.remove('dark')
    } else {
        document.documentElement.classList.add('dark')
    }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
