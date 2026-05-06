<?php
// On envoie le code HTTP 404 pour que les moteurs de recherche comprennent l'erreur
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 404 - Oups !</title>
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Comic Sans MS', cursive, sans-serif; /* Style un peu fun comme l'image */
            color: #4e342e; /* Marron café */
        }

        .bg-image {
            /* Remplacez 'lien_vers_votre_image.jpg' par le nom de votre fichier image */
            background-image: url('erreur404_cafe.png'); 
            height: 100%; 
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .content {
            background-color: rgba(255, 255, 255, 0.8); /* Fond blanc semi-transparent */
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            max-width: 80%;
        }

        h1 { font-size: 3rem; margin-bottom: 10px; }
        p { font-size: 1.5rem; }
        
        .btn-home {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background-color: #6d4c41;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: 0.3s;
        }
        
        .btn-home:hover { background-color: #3e2723; }
    </style>
</head>
<body>

<div class="bg-image">
    <div class="content">
        <h1>ERREUR 404</h1>
        <p>Oups ! Le café s'est renversé...<br>et cette page aussi.</p>
        <a href="/" class="btn-home">Retour à l'accueil</a>
    </div>
</div>

</body>
</html>
