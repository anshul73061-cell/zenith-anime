<?php
session_start();

$dbFile = 'database.json'; 

function loadDB() {
    global $dbFile;
    if (file_exists($dbFile)) {
        $data = json_decode(file_get_contents($dbFile), true);
        $_SESSION['db'] = $data;
        
        // patch to make sure old accounts don't break the new social stuff
        foreach($_SESSION['db']['users'] as $k => $v) {
            if(!isset($v['following'])) $_SESSION['db']['users'][$k]['following'] = [];
            if(!isset($v['inbox'])) $_SESSION['db']['users'][$k]['inbox'] = [];
        }
    } else {
        // brand new setup
        $_SESSION['db'] = [
            'users' => [
                'admin' => ['pass' => 'admin123', 'name' => 'God', 'avatar' => '🐉', 'role' => 'admin', 'following' => [], 'inbox' => []]
            ],
            'collections' => [], 'reviews' => [], 'rev_id' => 1, 'activity' => [],
            'versus' => ['title1' => 'Overlord', 'votes1' => 0, 'title2' => 'One Piece', 'votes2' => 0] 
        ];
        saveDB();
    }
}

function saveDB() {
    global $dbFile;
    file_put_contents($dbFile, json_encode($_SESSION['db'], JSON_PRETTY_PRINT));
}

function logAct($msg) {
    array_unshift($_SESSION['db']['activity'], $msg);
    if(count($_SESSION['db']['activity']) > 15) array_pop($_SESSION['db']['activity']);
}

// calculates titles dynamically
function calcTitle($u) {
    if(!isset($_SESSION['db']['collections'][$u])) return "[Silent Protagonist]";
    $cols = $_SESSION['db']['collections'][$u];
    $revs = 0; $glaze = 0;
    
    foreach($_SESSION['db']['reviews'] as $aid => $arr) {
        foreach($arr as $r) {
            if($r['username'] == $u) {
                $revs++;
                if($r['score'] == 5) $glaze++;
            }
        }
    }
    
    $watched = count($cols['watched'] ?? []);
    $later = count($cols['later'] ?? []);
    
    if($glaze >= 11) return "[Glaze Lord]";
    if($revs >= 5 && $watched == 0) return "[All Talk]";
    if($later > 50) return "[Infinite Void]";
    if($later > 20) return "[Procrastinator]";
    if($later > 5) return "[Window Shopper]";
    if($watched >= 50) return "[Domain Expansion]";
    if($watched >= 30) return "[Grass Avoider]";
    if($watched >= 15) return "[Seasoned Weeb]";
    if($revs >= 50) return "[Lore Master]";
    if($revs >= 25) return "[Cook License]";
    if($revs >= 15) return "[Certified Critic]";
    if($revs >= 5) return "[Hot Taker]";
    if($watched >= 1) return "[Casual]";
    
    return "[Silent Protagonist]";
}

loadDB();

// Handle AJAX json body
$json = file_get_contents('php://input');
$post = json_decode($json, true) ?? $_POST;
$act = $post['action'] ?? '';

switch ($act) {
    case 'signup':
        $usr = $_POST['user'];
        if (isset($_SESSION['db']['users'][$usr])) { header("Location: index.php?error=taken"); exit; }
        
        $_SESSION['db']['users'][$usr] = ['pass' => $_POST['pass'], 'name' => $_POST['name'], 'avatar' => '👤', 'role' => 'user', 'following' => [], 'inbox' => []];
        $_SESSION['db']['collections'][$usr] = ['later'=>[], 'watching'=>[], 'watched'=>[], 'dropped'=>[], 'favs'=>[], 'top3'=>[]];
        
        logAct("🎉 @$usr just joined the Zenith community!");
        saveDB();
        $_SESSION['curr_user'] = $usr;
        setcookie('auth_token', $usr, time() + (86400 * 30), "/"); 
        header("Location: index.php");
        break;

    case 'login':
        $usr = $_POST['user'];
        if (isset($_SESSION['db']['users'][$usr]) && $_SESSION['db']['users'][$usr]['pass'] == $_POST['pass']) {
            $_SESSION['curr_user'] = $usr;
            setcookie('auth_token', $usr, time() + (86400 * 30), "/");
            header("Location: index.php");
        } else { header("Location: index.php?error=invalid"); }
        break;

    case 'logout':
        session_destroy();
        setcookie('auth_token', '', time() - 3600, "/");
        header("Location: index.php");
        break;

    // --- AJAX STUFF ---

    case 'add_list':
        $usr = $_SESSION['curr_user'];
        $t = $post['type']; $id = $post['aid']; $title = $post['title'];

        // kill duplicates across lists
        foreach($_SESSION['db']['collections'][$usr] as $listType => $arr) {
            foreach($arr as $k => $item) {
                if($item['id'] == $id) unset($_SESSION['db']['collections'][$usr][$listType][$k]);
            }
            $_SESSION['db']['collections'][$usr][$listType] = array_values($_SESSION['db']['collections'][$usr][$listType]);
        }

        // podium logic
        if($t == 'top3') {
            if(count($_SESSION['db']['collections'][$usr]['top3']) >= 3) {
                array_shift($_SESSION['db']['collections'][$usr]['top3']);
            }
        }

        $epData = ($t == 'dropped') ? $post['drop_ep'] : (($t == 'watching') ? 0 : null);
        $_SESSION['db']['collections'][$usr][$t][] = [
            'id' => $id, 'title' => $title, 'img' => $post['img'], 'eps' => $epData
        ];
        
        if($t == 'top3') logAct("🏆 @$usr pinned $title to their Top 3 Podium.");
        if($t == 'dropped') logAct("🪦 @$usr dropped $title at episode $epData.");
        saveDB(); 
        echo json_encode(['status'=>'ok']);
        break;

    case 'update_ep':
        $usr = $_SESSION['curr_user'];
        $id = $post['aid'];
        $op = $post['op']; 

        if (isset($_SESSION['db']['collections'][$usr]['watching'])) {
            foreach ($_SESSION['db']['collections'][$usr]['watching'] as $k => $item) {
                if ($item['id'] == $id) {
                    $curr = isset($item['eps']) ? (int)$item['eps'] : 0;
                    if ($op == 'add') $curr++;
                    if ($op == 'sub' && $curr > 0) $curr--;
                    $_SESSION['db']['collections'][$usr]['watching'][$k]['eps'] = $curr;
                }
            }
        }
        saveDB();
        echo json_encode(['status'=>'ok']);
        break;

    case 'change_avatar':
        $usr = $_SESSION['curr_user'];
        $_SESSION['db']['users'][$usr]['avatar'] = $post['avatar'];
        saveDB();
        echo json_encode(['status'=>'ok']);
        break;

    case 'add_rev':
        $id = $post['aid']; $usr = $_SESSION['curr_user'];
        if (!isset($_SESSION['db']['reviews'][$id])) $_SESSION['db']['reviews'][$id] = [];
        
        $_SESSION['db']['reviews'][$id][] = [
            'id' => $_SESSION['db']['rev_id']++,
            'user' => $_SESSION['db']['users'][$usr]['name'], 
            'username' => $usr,
            'avatar' => $_SESSION['db']['users'][$usr]['avatar'],
            'title' => calcTitle($usr),
            'txt' => $post['rev_txt'],
            'score' => $post['tier_score'],
            'spoiler' => $post['is_spoiler'] ?? false,
            'wtakes' => [] 
        ];
        
        // ping followers
        $animeName = $post['anime_title'] ?? 'an anime';
        foreach($_SESSION['db']['users'] as $k => $uData) {
            if(in_array($usr, $uData['following'] ?? [])) {
                $_SESSION['db']['users'][$k]['inbox'][] = "🔔 @$usr just reviewed $animeName!";
            }
        }

        logAct("✍️ @$usr dropped a review for $animeName.");
        saveDB(); 
        echo json_encode(['status'=>'ok', 'reviews'=>$_SESSION['db']['reviews'][$id]]);
        break;
        
    case 'w_take':
        $aid = $post['aid']; $rid = $post['rid']; $usr = $_SESSION['curr_user'];
        foreach($_SESSION['db']['reviews'][$aid] as $k => $r) {
            if($r['id'] == $rid) {
                if(!isset($r['wtakes'])) $_SESSION['db']['reviews'][$aid][$k]['wtakes'] = [];
                $pos = array_search($usr, $_SESSION['db']['reviews'][$aid][$k]['wtakes']);
                
                if($pos !== false) unset($_SESSION['db']['reviews'][$aid][$k]['wtakes'][$pos]); 
                else $_SESSION['db']['reviews'][$aid][$k]['wtakes'][] = $usr; 
                
                $_SESSION['db']['reviews'][$aid][$k]['wtakes'] = array_values($_SESSION['db']['reviews'][$aid][$k]['wtakes']);
            }
        }
        saveDB();
        echo json_encode(['status'=>'ok', 'reviews'=>$_SESSION['db']['reviews'][$aid]]);
        break;

    case 'vote_vs':
        $side = $post['side'];
        $_SESSION['db']['versus']["votes$side"]++;
        saveDB();
        echo json_encode(['v1' => $_SESSION['db']['versus']['votes1'], 'v2' => $_SESSION['db']['versus']['votes2']]);
        break;

    case 'get_profile':
        $tgt = $post['target'];
        $me = $_SESSION['curr_user'] ?? '';
        
        $mutual = [];
        if($me && $me !== $tgt) {
            $myLater = $_SESSION['db']['collections'][$me]['later'] ?? [];
            $tgtLater = $_SESSION['db']['collections'][$tgt]['later'] ?? [];
            foreach($tgtLater as $tShow) {
                foreach($myLater as $mShow) {
                    if($tShow['id'] == $mShow['id']) $mutual[] = $tShow;
                }
            }
        }
        
        $followers = 0;
        foreach($_SESSION['db']['users'] as $u) { if(in_array($tgt, $u['following'] ?? [])) $followers++; }
        $is_following = $me ? in_array($tgt, $_SESSION['db']['users'][$me]['following'] ?? []) : false;
        $top3 = $_SESSION['db']['collections'][$tgt]['top3'] ?? [];

        echo json_encode([
            'status' => 'ok',
            'user' => $_SESSION['db']['users'][$tgt],
            'title' => calcTitle($tgt),
            'followers' => $followers,
            'is_following' => $is_following,
            'mutual' => $mutual,
            'top3' => $top3
        ]);
        break;

    case 'toggle_follow':
        $tgt = $post['target']; $me = $_SESSION['curr_user'];
        $pos = array_search($tgt, $_SESSION['db']['users'][$me]['following']);
        if($pos !== false) {
            unset($_SESSION['db']['users'][$me]['following'][$pos]);
        } else {
            $_SESSION['db']['users'][$me]['following'][] = $tgt;
            $_SESSION['db']['users'][$tgt]['inbox'][] = "👋 @$me just started following you!";
        }
        $_SESSION['db']['users'][$me]['following'] = array_values($_SESSION['db']['users'][$me]['following']);
        saveDB();
        echo json_encode(['status'=>'ok']);
        break;

    case 'clear_inbox':
        $usr = $_SESSION['curr_user'];
        $_SESSION['db']['users'][$usr]['inbox'] = [];
        saveDB();
        echo json_encode(['status'=>'ok']);
        break;
}
?>

