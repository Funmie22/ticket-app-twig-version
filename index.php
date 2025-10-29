<?php

require_once __DIR__ . '/vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'auth/login':
        echo $twig->render('login.html.twig');
        break;

    case 'auth/signup':
        echo $twig->render('signup.html.twig');
        break;

    case 'dashboard':
        echo $twig->render('dashboard.html.twig');
        break;

    case 'tickets':
        echo $twig->render('tickets.html.twig');
        break;

    default:
        echo $twig->render('app_landing.twig');
        break;
}
