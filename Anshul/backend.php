<?php
session_start();

$dbFile = 'database.json'; 

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
        // first time setup
        $_SESSION['users'] = [
            'admin' => ['pass' => 'admin123', 'name' => 'God', 'phone' => '000', 'role' => 'admin', 'avatar' => '🐉']
        ];
        $_SESSION['collections'] = [];
        $_SESSION['reviews'] = [];
        $_SESSION['rev_id'] = 1; 
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

loadDB(); // fire it up

$act = $_POST['action'] ?? '';

switch ($act) {
    case 'signup':
        $usr = $_POST['user'];
        
        if (isset($_SESSION['users'][$usr])) {
            header("Location: index.php?error=taken");
            exit;
        }
        
        if ($_POST['otp'] == '1234') {
            $_SESSION['users'][$usr] = [
                'pass' => $_POST['pass'], 
                'name' => $_POST['name'], 
                'phone' => $_POST['phone'], 
                'role' => 'user',
                'avatar' => '👤' // default generic avatar
            ];
            
            // added 'watching' list for the tracker
            $_SESSION['collections'][$usr] = ['later' => [], 'watching' => [], 'watched' => [], 'favs' => []];
            saveDB();
            
            $_SESSION['curr_user'] = $usr;
            $_SESSION['curr_name'] = $_POST['name'];
            $_SESSION['role'] = 'user';
            
            setcookie('auth_token', $usr, time() + (86400 * 30), "/"); 
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
        setcookie('auth_token', '', time() - 3600, "/");
        header("Location: index.php");
        break;

    case 'add_list':
        $usr = $_SESSION['curr_user'];
        $t = $_POST['type']; 
        $id = $_POST['aid'];

        $dupe = false;
        foreach ($_SESSION['collections'][$usr][$t] as $i) {
            if ($i['id'] == $id) $dupe = true;
        }

        if (!$dupe) {
            // add episode counter if they added it to watching
            $episodes = ($t == 'watching') ? 0 : null;
            
            $_SESSION['collections'][$usr][$t][] = [
                'id' => $id, 
                'title' => $_POST['title'], 
                'img' => $_POST['img'],
                'eps' => $episodes
            ];
            saveDB(); 
        }
        header("Location: index.php?open=" . $id);
        break;

    case 'update_ep':
        // hacky way to do + and - math on episodes
        $usr = $_SESSION['curr_user'];
        $id = $_POST['aid'];
        $math = $_POST['math']; 
        
        foreach ($_SESSION['collections'][$usr]['watching'] as $k => $item) {
            if ($item['id'] == $id) {
                if ($math == 'plus') $_SESSION['collections'][$usr]['watching'][$k]['eps']++;
                if ($math == 'minus' && $_SESSION['collections'][$usr]['watching'][$k]['eps'] > 0) $_SESSION['collections'][$usr]['watching'][$k]['eps']--;
            }
        }
        saveDB();
        header("Location: index.php?tab=col");
        break;

    case 'change_avatar':
        $usr = $_SESSION['curr_user'];
        $_SESSION['users'][$usr]['avatar'] = $_POST['avatar_emoji'];
        saveDB();
        header("Location: index.php?tab=settings");
        break;

    case 'add_rev':
        $id = $_POST['aid'];
        if (!isset($_SESSION['reviews'][$id])) $_SESSION['reviews'][$id] = [];
        
        // checking if spoiler checkbox was ticked
        $spoiler = isset($_POST['is_spoiler']) ? true : false;
        
        $_SESSION['reviews'][$id][] = [
            'id' => $_SESSION['rev_id']++,
            'user' => $_SESSION['curr_name'], 
            'username' => $_SESSION['curr_user'],
            'avatar' => $_SESSION['users'][$_SESSION['curr_user']]['avatar'] ?? '👤',
            'txt' => $_POST['rev_txt'],
            'score' => $_POST['tier_score'],
            'spoiler' => $spoiler
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
