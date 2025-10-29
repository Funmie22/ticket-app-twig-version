<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader, ['cache' => false]);

function nowMs() { return round(microtime(true) * 1000); }

function ensure_session_keys() {
    if (!isset($_SESSION['tickets'])) $_SESSION['tickets'] = [];
    if (!isset($_SESSION['session_token'])) $_SESSION['session_token'] = null;
    if (!isset($_SESSION['session_exp'])) $_SESSION['session_exp'] = 0;
}

ensure_session_keys();

function loginMock($email, $password) {
    $okEmails = ['test@example.com','user@example.com'];
    if (in_array($email, $okEmails) && $password === 'password123') {
        $_SESSION['session_token'] = base64_encode($email . ':' . time());
        $_SESSION['session_exp'] = time() + 24 * 3600;
        return true;
    }
    return false;
}

function signupMock($email, $password) {
    $_SESSION['session_token'] = base64_encode($email . ':' . time());
    $_SESSION['session_exp'] = time() + 24 * 3600;
    return true;
}

function logout() {
    $_SESSION['session_token'] = null;
    $_SESSION['session_exp'] = 0;
}

function isAuthenticated() {
    if (empty($_SESSION['session_token'])) return false;
    if (time() > ($_SESSION['session_exp'] ?? 0)) {
        logout();
        return false;
    }
    return true;
}

function loadTickets() {
    return $_SESSION['tickets'] ?? [];
}

function saveTickets($list) {
    $_SESSION['tickets'] = $list;
}

function createTicket($payload) {
    $id = (string) round(microtime(true) * 1000);
    $ticket = [
        'id' => $id,
        'title' => trim($payload['title'] ?? ''),
        'description' => trim($payload['description'] ?? ''),
        'status' => $payload['status'] ?? 'open',
        'priority' => $payload['priority'] ?? 'normal',
        'createdAt' => date(DATE_ATOM)
    ];
    $list = loadTickets();
    array_unshift($list, $ticket);
    saveTickets($list);
    return $ticket;
}

function updateTicket($id, $patch) {
    $list = loadTickets();
    foreach ($list as $idx => $t) {
        if ($t['id'] === $id) {
            $updated = array_merge($t, $patch);
            if (!trim($updated['title'])) throw new Exception("Title required");
            $list[$idx] = $updated;
            saveTickets($list);
            return $updated;
        }
    }
    throw new Exception("Ticket not found");
}

function deleteTicketById($id) {
    $list = loadTickets();
    $list = array_filter($list, function($t) use ($id) { return $t['id'] !== $id; });
    $list = array_values($list);
    saveTickets($list);
    return $list;
}

$path = $_GET['page'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($method === 'POST') {
    if ($path === 'auth/login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (loginMock($email, $password)) {
            $_SESSION['flash'] = ['type'=>'success','message'=>'Login successful — redirecting...'];
            header('Location: /?page=dashboard');
            exit;
        } else {
            $_SESSION['flash'] = ['type'=>'error','message'=>'Invalid credentials.'];
            header('Location: /?page=auth/login');
            exit;
        }
    }

    if ($path === 'auth/signup') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        signupMock($email, $password);
        $_SESSION['flash'] = ['type'=>'success','message'=>'Account created — redirecting...'];
        header('Location: /?page=dashboard');
        exit;
    }

    if ($path === 'auth/logout') {
        logout();
        header('Location: /?page=home');
        exit;
    }

    if ($path === 'tickets/create') {
        if (!isAuthenticated()) { $_SESSION['flash']=['type'=>'error','message'=>'Session expired']; header('Location: /?page=auth/login'); exit; }
        $payload = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'open',
            'priority' => $_POST['priority'] ?? 'normal'
        ];
        try {
            createTicket($payload);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Ticket created.'];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type'=>'error','message'=>'Failed to create ticket.'];
        }
        header('Location: /?page=tickets');
        exit;
    }

    if ($path === 'tickets/update') {
        if (!isAuthenticated()) { $_SESSION['flash']=['type'=>'error','message'=>'Session expired']; header('Location: /?page=auth/login'); exit; }
        $id = $_POST['id'] ?? '';
        $patch = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'open',
            'priority' => $_POST['priority'] ?? 'normal'
        ];
        try {
            updateTicket($id, $patch);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Ticket updated.'];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type'=>'error','message'=>'Failed to update ticket.'];
        }
        header('Location: /?page=tickets');
        exit;
    }

    if ($path === 'tickets/delete') {
        if (!isAuthenticated()) { $_SESSION['flash']=['type'=>'error','message'=>'Session expired']; header('Location: /?page=auth/login'); exit; }
        $id = $_POST['id'] ?? '';
        try {
            deleteTicketById($id);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Ticket deleted.'];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type'=>'error','message'=>'Failed to delete ticket.'];
        }
        header('Location: /?page=tickets');
        exit;
    }
}

$params = [
    'isAuth' => isAuthenticated(),
    'flash' => $flash,
    'tickets' => loadTickets()
];

switch ($path) {
    case 'auth/login':
        echo $twig->render('pages/auth/login.html.twig', $params);
        break;
    case 'auth/signup':
        echo $twig->render('pages/auth/signup.html.twig', $params);
        break;
    case 'dashboard':
        if (!isAuthenticated()) { header('Location: /?page=auth/login&reason=expired'); exit; }
        echo $twig->render('pages/dashboard/dashboard.html.twig', $params);
        break;
    case 'tickets':
        if (!isAuthenticated()) { header('Location: /?page=auth/login&reason=expired'); exit; }
        echo $twig->render('pages/tickets/tickets.html.twig', $params);
        break;
    default:
        echo $twig->render('pages/app_landing.twig', $params);
        break;
}
