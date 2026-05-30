<?php
session_start();

// TODO: move this to a real MySQL database when I learn how
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [
        'admin' => ['pass' => 'admin123', 'name' => 'Site Admin', 'phone' => '0000000000', 'role' => 'admin']
    ];
    $_SESSION['collections'] = [];
    $_SESSION['reviews'] = [];
    $_SESSION['rev_id'] = 1; // auto-increment hack
}

// var_dump($_POST); die(); // <-- uncomment this to debug form submissions

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'signup':
        $u = $_POST['user'];
        // check if username is taken
        if (isset($_SESSION['users'][$u])) {
            header("Location: index.php?error=taken");
            die();
        }
        
        // pretending otp works
        if ($_POST['otp'] == '1234') {
            $_SESSION['users'][$u] = [
                'pass' => $_POST['pass'], 
                'name' => $_POST['name'], 
                'phone' => $_POST['phone'], 
                'role' => 'user'
            ];
            // setup empty lists for the new guy
            $_SESSION['collections'][$u] = ['later' => [], 'watched' => [], 'favs' => []];
            
            // auto-login
            $_SESSION['curr_user'] = $u;
            $_SESSION['curr_name'] = $_POST['name'];
            $_SESSION['role'] = 'user';
            
            header("Location: index.php?welcome=1");
        }
        break;

    case 'login':
        $u = $_POST['user'];
        $p = $_POST['pass'];
        
        if (isset($_SESSION['users'][$u]) && $_SESSION['users'][$u]['pass'] == $p) {
            $_SESSION['curr_user'] = $u;
            $_SESSION['curr_name'] = $_SESSION['users'][$u]['name'];
            $_SESSION['role'] = $_SESSION['users'][$u]['role'];
            
            // remember me for 30 days
            setcookie('remembered_user', $u, time() + (86400 * 30), "/");
            header("Location: index.php?welcome=1");
        } else {
            header("Location: index.php?error=invalid");
        }
        break;

    case 'logout':
        session_destroy();
        header("Location: index.php");
        break;

    case 'add_list':
        $u = $_SESSION['curr_user'];
        $type = $_POST['type']; 
        $aid = $_POST['aid'];

        // check if it's already in the list so we don't get duplicates
        $already_exists = false;
        foreach ($_SESSION['collections'][$u][$type] as $item) {
            if ($item['id'] == $aid) $already_exists = true;
        }

        if (!$already_exists) {
            $_SESSION['collections'][$u][$type][] = [
                'id' => $aid, 
                'title' => $_POST['title'], 
                'img' => $_POST['img']
            ];
        }
        // kick em back to the anime they were looking at
        header("Location: index.php?open=" . $aid);
        break;

    case 'add_rev':
        $aid = $_POST['aid'];
        if (!isset($_SESSION['reviews'][$aid])) $_SESSION['reviews'][$aid] = [];
        
        $_SESSION['reviews'][$aid][] = [
            'id' => $_SESSION['rev_id']++,
            'user' => $_SESSION['curr_name'], 
            'username' => $_SESSION['curr_user'],
            'txt' => $_POST['rev_txt']
        ];
        header("Location: index.php?open=" . $aid);
        break;

    case 'del_rev':
        $aid = $_POST['aid'];
        foreach ($_SESSION['reviews'][$aid] as $k => $r) {
            if ($r['id'] == $_POST['rid']) {
                unset($_SESSION['reviews'][$aid][$k]);
            }
        }
        header("Location: index.php?open=" . $aid);
        break;
}
?>