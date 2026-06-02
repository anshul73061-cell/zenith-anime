<?php
session_start();

$dbFile = 'database.json'; // don't change this path

function loadDB() {
    global $dbFile;
    if (file_exists($dbFile)) {
        $raw = file_get_contents($dbFile);
        $data = json_decode($raw, true);
        
        $_SESSION['users'] = $data['users'];
        $_SESSION['collections'] = $data['collections'];
        $_SESSION['reviews'] = $data['reviews'];
        $_SESSION['rev_id'] = $data['rev_id'];
    } else {
        // first run setup
        $_SESSION['users'] = [
            'admin' => ['pass' => 'admin123', 'name' => 'God', 'phone' => '000', 'role' => 'admin']
        ];
        $_SESSION['collections'] = [];
        $_SESSION['reviews'] = [];
        $_SESSION['rev_id'] = 1; // idk if there's a better way to do this but it works
        saveDB();
    }
}

function saveDB() {
    global $dbFile;
    $dump = [
        'users' => $_SESSION['users'],
        'collections' => $_SESSION['collections'],
        'reviews' => $_SESSION['reviews'],
        'rev_id' => $_SESSION['rev_id']
    ];
    file_put_contents($dbFile, json_encode($dump, JSON_PRETTY_PRINT));
}

loadDB(); // init

$act = $_POST['action'] ?? '';
// print_r($_POST); die(); // uncomment if form breaks again

switch ($act) {
    case 'signup':
        $usr = $_POST['user'];
        
        // checking if taken
        if (isset($_SESSION['users'][$usr])) {
            header("Location: index.php?error=taken");
            exit;
        }
        
        // TODO: actually connect to a real SMS gateway for OTP later
        if ($_POST['otp'] == '1234') {
            $_SESSION['users'][$usr] = [
                'pass' => $_POST['pass'], 
                'name' => $_POST['name'], 
                'phone' => $_POST['phone'], 
                'role' => 'user'
            ];
            
            $_SESSION['collections'][$usr] = ['later' => [], 'watched' => [], 'favs' => []];
            saveDB();
            
            $_SESSION['curr_user'] = $usr;
            $_SESSION['curr_name'] = $_POST['name'];
            $_SESSION['role'] = 'user';
            
            setcookie('auth_token', $usr, time() + (86400 * 30), "/"); // 30 days
            header("Location: index.php?welcome=1");
        }
        break;

    case 'login':
        $usr = $_POST['user'];
        $pwd = $_POST['pass'];
        
        if (isset($_SESSION['users'][$usr]) && $_SESSION['users'][$usr]['pass'] == $pwd) {
            $_SESSION['curr_user'] = $usr;
            $_SESSION['curr_name'] = $_SESSION['users'][$usr]['name'];
            $_SESSION['role'] = $_SESSION['users'][$usr]['role'];
            
            setcookie('auth_token', $usr, time() + (86400 * 30), "/");
            header("Location: index.php?welcome=1");
        } else {
            header("Location: index.php?error=invalid");
        }
        break;

    case 'logout':
        session_destroy();
        setcookie('auth_token', '', time() - 3600, "/"); // kill cookie
        header("Location: index.php");
        break;

    case 'add_list':
        $usr = $_SESSION['curr_user'];
        $t = $_POST['type']; 
        $id = $_POST['aid'];

        // check dupes
        $dupe = false;
        foreach ($_SESSION['collections'][$usr][$t] as $i) {
            if ($i['id'] == $id) $dupe = true;
        }

        if (!$dupe) {
            $_SESSION['collections'][$usr][$t][] = [
                'id' => $id, 
                'title' => $_POST['title'], 
                'img' => $_POST['img']
            ];
            saveDB(); 
        }
        header("Location: index.php?open=" . $id);
        break;

    case 'add_rev':
        $id = $_POST['aid'];
        if (!isset($_SESSION['reviews'][$id])) $_SESSION['reviews'][$id] = [];
        
        $_SESSION['reviews'][$id][] = [
            'id' => $_SESSION['rev_id']++,
            'user' => $_SESSION['curr_name'], 
            'username' => $_SESSION['curr_user'],
            'txt' => $_POST['rev_txt']
        ];
        
        saveDB(); 
        header("Location: index.php?open=" . $id);
        break;

    case 'del_rev':
        $id = $_POST['aid'];
        foreach ($_SESSION['reviews'][$id] as $k => $r) {
            if ($r['id'] == $_POST['rid']) {
                unset($_SESSION['reviews'][$id][$k]);
            }
        }
        saveDB(); 
        header("Location: index.php?open=" . $id);
        break;
}
?>
