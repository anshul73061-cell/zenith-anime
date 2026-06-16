<?php 
session_start(); 
require_once('backend.php');
$u = $_SESSION['curr_user'] ?? null;
$my_avatar = $u ? $_SESSION['db']['users'][$u]['avatar'] : '👤';
$my_inbox = $u ? ($_SESSION['db']['users'][$u]['inbox'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Explore - Zenith Anime</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <nav class="nav">
        <div class="nav-left">
            <div class="logo" onclick="window.location='index.php'">Zenith<span>Anime</span></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="explore.php" style="color:var(--red);">Explore</a>
                <?php if($u): ?><a href="index.php?tab=col">My Lists</a><?php endif; ?>
            </div>
        </div>
        
        <div class="user-area">
            <?php if ($u): ?>
                <div class="bell-container" onclick="toggleInbox()">
                    🔔 <?php if(count($my_inbox) > 0) echo "<span class='badge'>".count($my_inbox)."</span>"; ?>
                    <div id="inbox-drop" class="inbox-drop">
                        <?php 
                        if(empty($my_inbox)) echo "<p style='padding:10px; color:grey;'>No new alerts.</p>";
                        else {
                            foreach(array_reverse($my_inbox) as $msg) echo "<div class='msg'>$msg</div>";
                            echo "<button class='btn block-btn' style='background:#333; color:white;' onclick='clearInbox()'>Clear All</button>";
                        }
                        ?>
                    </div>
                </div>
                <span class="user-name"><?= $my_avatar ?> <?= htmlspecialchars($_SESSION['db']['users'][$u]['name']) ?></span>
            <?php else: ?>
                <button class="btn main-btn" onclick="window.location='index.php'">Login</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="cinematic-hero">
        <div class="glass-title">
            <h1>Discover Your Next Obsession</h1>
            <p>Filter through thousands of anime to find exactly what you want.</p>
        </div>
    </div>

    <div class="explore-container">
        <div class="filter-sidebar">
            <h3>Filters</h3>
            <label>Genre</label>
            <select id="ex-genre">
                <option value="">All Genres</option>
                <option value="1">Action</option>
                <option value="22">Romance</option>
                <option value="36">Slice of Life</option>
                <option value="10">Fantasy</option>
                <option value="8">Drama</option>
            </select>
            
            <label>Status</label>
            <select id="ex-status">
                <option value="">Any</option>
                <option value="airing">Airing</option>
                <option value="complete">Complete</option>
                <option value="upcoming">Upcoming</option>
            </select>
            <button class="btn main-btn block-btn" style="margin-top:20px;" onclick="runExplore()">Apply Filters</button>
        </div>
        
        <div class="explore-results">
            <div class="grid" id="explore-grid"><p style="color:grey;">Select filters and hit apply.</p></div>
        </div>
    </div>

    <script src="app.js"></script>
    <script>
        async function runExplore() {
            document.getElementById('explore-grid').innerHTML = '<p>Loading...</p>';
            let g = document.getElementById('ex-genre').value;
            let s = document.getElementById('ex-status').value;
            let ep = `/anime?order_by=score&sort=desc`;
            
            if(g) ep += `&genres=${g}`;
            if(s) ep += `&status=${s}`;
            
            await getGrid(ep, 'explore-grid', 24);
        }
        runExplore(); 
    </script>
</body>
</html>

