<?php 
session_start(); 
require_once('backend.php');

// cookie resume
if (!isset($_SESSION['curr_user']) && isset($_COOKIE['auth_token'])) {
    $u = $_COOKIE['auth_token'];
    if (isset($_SESSION['db']['users'][$u])) $_SESSION['curr_user'] = $u;
}

$u = $_SESSION['curr_user'] ?? null;
$my_avatar = $u ? $_SESSION['db']['users'][$u]['avatar'] : '👤';
$my_title = $u ? calcTitle($u) : '';
$my_inbox = $u ? ($_SESSION['db']['users'][$u]['inbox'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Zenith Anime</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <script>
        // passing PHP vars to JS safely
        const activeUser = "<?= $u ?? '' ?>";
        var revDB = <?= json_encode($_SESSION['db']['reviews'] ?? new stdClass()) ?>;
    </script>

    <!-- Netflix-Style Nav -->
    <nav class="nav">
        <div class="nav-left">
            <div class="logo" onclick="window.location='index.php'">Zenith<span>Anime</span></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="explore.php">Explore</a>
                <?php if($u): ?><a href="#" onclick="switchTab('col')">My Lists</a><?php endif; ?>
            </div>
        </div>
        
        <div class="nav-mid">
            <input type="text" id="searchBar" placeholder="Search..." onkeyup="if(event.key === 'Enter') doSearch()">
            <button class="btn alt-btn" onclick="getRandom()" title="I'm feeling lucky">🎲</button>
        </div>
        
        <div class="user-area">
            <?php if ($u): ?>
                <!-- Notification Bell -->
                <div class="bell-container" onclick="toggleInbox()">
                    🔔 <?php if(count($my_inbox) > 0) echo "<span class='badge'>".count($my_inbox)."</span>"; ?>
                    <div id="inbox-drop" class="inbox-drop">
                        <?php 
                        if(empty($my_inbox)) echo "<p style='padding:10px; color:grey; text-align:center;'>No new alerts.</p>";
                        else {
                            foreach(array_reverse($my_inbox) as $msg) echo "<div class='msg'>$msg</div>";
                            echo "<button class='btn block-btn' style='background:#333; color:white;' onclick='clearInbox()'>Clear All</button>";
                        }
                        ?>
                    </div>
                </div>

                <span class="user-name" onclick="switchTab('settings')">
                    <?= $my_avatar ?> <?= htmlspecialchars($_SESSION['db']['users'][$u]['name']) ?>
                </span>
                <a href="backend.php?action=logout" class="btn alt-btn" style="padding:5px 10px;">Logout</a>
            <?php else: ?>
                <button class="btn main-btn" onclick="showModal('auth-modal')">Login</button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Quote & Ticker -->
    <div id="quote-banner" class="quote-banner">"Loading wisdom..." - Zenith</div>

    <div class="ticker-wrap">
        <div class="ticker-move">
            <?php 
            if(empty($_SESSION['db']['activity'])) echo "<div class='ticker-item'>No recent activity. Do something!</div>";
            foreach($_SESSION['db']['activity'] as $act) echo "<div class='ticker-item'>$act</div>";
            ?>
        </div>
    </div>

    <!-- MAIN HOME VIEW -->
    <div id="tab-home" class="tab active" style="display:flex; padding: 2rem;">
        
        <div style="flex: 1;">
            <h3 class="sec-title" id="main-header">🔥 Trending Now</h3>
            <div class="grid" id="pop-grid"></div>
            
            <h3 class="sec-title">Highly Anticipated Releases</h3>
            <div class="grid" id="upcoming-grid"></div>
        </div>
        
        <!-- Sidebar Versus Clash -->
        <div class="sidebar">
            <div class="vs-card">
                <h3>⚔️ Daily Clash</h3>
                <?php $vs = $_SESSION['db']['versus']; $total = $vs['votes1'] + $vs['votes2']; $total = $total == 0 ? 1 : $total; ?>
                <div class="vs-row">
                    <button class="btn alt-btn" onclick="voteVs(1)"><?= $vs['title1'] ?></button>
                    <span id="v1-pct"><?= round(($vs['votes1']/$total)*100) ?>%</span>
                </div>
                <div class="vs-row" style="margin-top:10px;">
                    <button class="btn alt-btn" onclick="voteVs(2)"><?= $vs['title2'] ?></button>
                    <span id="v2-pct"><?= round(($vs['votes2']/$total)*100) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MY LISTS / DASHBOARD -->
    <div id="tab-col" class="tab">
        <h2 class="sec-title">My Collection</h2>
        <?php 
        if ($u): 
            $lists = $_SESSION['db']['collections'][$u];
            // Added Top 3 array visually here
            $cats = ['top3'=>'🏆 Top 3 Podium', 'watching'=>'Currently Watching', 'later'=>'Watch Later', 'watched'=>'Finished', 'favs'=>'Favorites', 'dropped'=>'Graveyard 🪦'];
            
            foreach ($cats as $k => $title):
        ?>
            <h3><?= $title ?></h3>
            <div class="grid">
                <?php 
                if (empty($lists[$k])) echo "<p style='color:grey;'>Empty.</p>";
                foreach ($lists[$k] as $a): 
                ?>
                    <div class="card" onclick="loadAnime(<?= $a['id'] ?>)">
                        <img src="<?= $a['img'] ?>">
                        <h4><?= htmlspecialchars($a['title']) ?></h4>
                        <?php if($k == 'dropped'): ?>
                            <div style="background:#222; font-size:0.8rem; padding:5px; text-align:center;">Rage quit at Ep: <?= $a['eps'] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ANIME MODAL (Where reviews and buttons live) -->
    <div id="anime-modal" class="modal">
        <div class="modal-box">
            <span class="close" onclick="hideModal('anime-modal')">&times;</span>
            <div id="loading">Loading details...</div>
            
            <div id="anime-data" style="display:none;">
                <div class="split">
                    <img id="m-img" src="">
                    <div class="info">
                        <h2 id="m-title">Title</h2>
                        <div class="tags">
                            <span id="m-score">⭐</span>
                            <span id="m-eps"></span>
                            <button id="trailer-btn" class="btn main-btn" style="padding: 2px 8px; font-size:0.8rem; display:none;" onclick="playTrailer()">▶ Trailer</button>
                        </div>
                        <p id="m-desc"></p>
                        <div id="trailer-container" style="display:none; margin-top:10px;"></div>
                        
                        <?php if ($u): ?>
                            <div class="btn-group" style="flex-wrap: wrap;">
                                <button class="btn alt-btn" onclick="saveToList('watching')">▶ Watching</button>
                                <button class="btn alt-btn" onclick="saveToList('later')">⏱ Later</button>
                                <button class="btn alt-btn" onclick="saveToList('watched')">✅ Done</button>
                                <button class="btn alt-btn" onclick="saveToList('favs')">❤️ Fav</button>
                                <button class="btn alt-btn" onclick="saveToList('top3')" style="border-color:gold; color:gold;">🏆 Pin Top 3</button>
                                <button class="btn alt-btn" onclick="saveToList('dropped')" style="border-color:#ff2a4b; color:#ff2a4b;">🪦 Drop</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="reviews-section">
                    <div style="background: var(--bg); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                        <strong>Zenith Verdict: </strong> <span id="zenith-verdict" style="color: var(--red); font-weight: bold;">Calculating...</span>
                    </div>

                    <?php if ($u): ?>
                        <div class="rev-form">
                            <select id="tier_score" required>
                                <option value="" disabled selected>Rate it...</option>
                                <option value="5">🐐 Peak Fiction</option>
                                <option value="4">🔥 Really Good</option>
                                <option value="3">👍 One-Time Watch</option>
                                <option value="2">🍿 Just a Timepass</option>
                                <option value="1">💀 Why Does This Exist?</option>
                            </select>
                            <textarea id="rev_txt" placeholder="Write something..." required></textarea>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label style="font-size:0.9rem;"><input type="checkbox" id="is_spoiler"> ⚠️ Spoilers</label>
                                <button class="btn main-btn" onclick="postReview()">Post Review</button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div id="rev-list" style="margin-top:20px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- USER PROFILE MODAL (Radar & Top 3) -->
    <div id="user-modal" class="modal">
        <div class="modal-box" style="max-width: 500px; text-align:center;">
            <span class="close" onclick="hideModal('user-modal')">&times;</span>
            <div id="u-prof-loading">Loading radar...</div>
            
            <div id="u-prof-data" style="display:none;">
                <div style="font-size: 5rem;" id="u-avatar">👤</div>
                <h2 style="margin:5px 0;">@<span id="u-name">name</span></h2>
                <p style="color:var(--red); font-weight:bold; margin-top:0;" id="u-title">[Title]</p>
                <p style="color:grey; font-size:0.9rem;"><span id="u-followers">0</span> Followers</p>
                
                <button id="follow-btn" class="btn alt-btn" style="margin-bottom:20px;" onclick="toggleFollow()">Follow</button>
                
                <!-- Mutual Backlog -->
                <div id="mutual-box" style="background:var(--bg); border:1px solid var(--border); padding:15px; border-radius:8px; text-align:left; display:none; margin-bottom:15px;">
                    <h4 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:5px;">📡 Mutual Watch Later Radar</h4>
                    <ul id="mutual-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0;"></ul>
                </div>

                <!-- Top 3 Podium Flex -->
                <div id="top3-box" style="background:var(--bg); border:1px solid gold; padding:15px; border-radius:8px; text-align:left; display:none;">
                    <h4 style="margin-top:0; color:gold; border-bottom:1px solid var(--border); padding-bottom:5px;">🏆 Top 3 Podium</h4>
                    <ul id="top3-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0; color:white;"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- LOGIN MODAL -->
    <div id="auth-modal" class="modal">
        <div class="modal-box auth-box">
            <span class="close" onclick="hideModal('auth-modal')">&times;</span>
            <div id="f-login">
                <h2>Login</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="user" placeholder="Username" required>
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
        <section class="hero">
            <h3>🔥 Trending</h3>
            <div class="slider-track" id="hero-track"></div>
        </section>
        <h3 class="sec-title">Most Popular</h3>
        <div class="grid" id="pop-grid"></div>
        <h3 class="sec-title">Top Rated</h3>
        <div class="grid" id="top-grid"></div>
        <h3 class="sec-title">Recent</h3>
        <div class="grid" id="recent-grid"></div>
    </div>

    <div id="tab-col" class="tab">
        <h2 class="sec-title">My Anime</h2>
        <?php 
        if (isset($_SESSION['curr_user']) && $_SESSION['role'] != 'admin'): 
            $lists = $_SESSION['collections'][$_SESSION['curr_user']];
            $cats = ['watching' => 'Currently Watching', 'later' => 'Watch Later', 'watched' => 'Finished', 'favs' => 'Favorites'];
            
            foreach ($cats as $k => $title):
        ?>
            <h3><?= $title ?></h3>
            <div class="grid">
                <?php 
                if (empty($lists[$k])) echo "<p style='color:grey; padding-left:20px;'>Empty list.</p>";
                foreach ($lists[$k] as $a): 
                ?>
                    <div class="card">
                        <img src="<?= $a['img'] ?>" onclick="loadAnime(<?= $a['id'] ?>)">
                        <h4><?= htmlspecialchars($a['title']) ?></h4>
                        
                        <?php if($k == 'watching'): ?>
                        <div class="tracker">
                            <span>Eps: <?= $a['eps'] ?></span>
                            <form action="backend.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="update_ep">
                                <input type="hidden" name="aid" value="<?= $a['id'] ?>">
                                <button name="math" value="minus" class="ep-btn">-</button>
                                <button name="math" value="plus" class="ep-btn">+</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div id="tab-admin" class="tab">
        <h2 class="sec-title">All Reviews (Admin)</h2>
        <div class="card-box">
            <?php
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
                $chk = true;
                foreach ($_SESSION['reviews'] as $aid => $revs) {
                    foreach ($revs as $r) {
                        $chk = false;
                        echo "<div class='rev-box'>
                                <b>{$r['user']}</b> (@{$r['username']}) - Anime: $aid<br>{$r['txt']}
                                <form action='backend.php' method='POST' style='float:right; margin-top:-25px;'>
                                    <input type='hidden' name='action' value='del_rev'>
                                    <input type='hidden' name='aid' value='$aid'>
                                    <input type='hidden' name='rid' value='{$r['id']}'>
                                    <button class='btn-del'>Del</button>
                                </form>
                              </div>";
                    }
                }
                if ($chk) echo "<p>No reviews found.</p>";
            }
            ?>
        </div>
    </div>

    <div id="anime-modal" class="modal">
        <div class="modal-box">
            <span class="close" onclick="hideModal('anime-modal')">&times;</span>
            <div id="loading">Loading...</div>
            
            <div id="anime-data" style="display:none;">
                <div class="split">
                    <img id="m-img" src="">
                    <div class="info">
                        <h2 id="m-title">Title</h2>
                        <div class="tags">
                            <span id="m-score">⭐</span>
                            <span id="m-eps"></span>
                        </div>
                        <p id="m-desc"></p>
                        
                        <?php if (isset($_SESSION['curr_user'])): ?>
                            <div class="btn-group" style="flex-wrap: wrap;">
                                <?php foreach(['watching' => '▶ Watching', 'later' => '⏱ Later', 'watched' => '✅ Done', 'favs' => '❤️ Fav'] as $k => $v): ?>
                                <form action="backend.php" method="POST" style="flex: 1 1 45%;">
                                    <input type="hidden" name="action" value="add_list">
                                    <input type="hidden" name="type" value="<?= $k ?>">
                                    <input type="hidden" name="aid" class="form-aid">
                                    <input type="hidden" name="title" class="form-title">
                                    <input type="hidden" name="img" class="form-img">
                                    <button class="btn alt-btn block-btn" style="margin-top:5px;"><?= $v ?></button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--red); font-size:14px;">Login to save to lists.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="reviews-section">
                    <h3>Community Reviews</h3>
                    
                    <div style="background: var(--bg); padding: 10px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 15px;">
                        <strong>Zenith Verdict: </strong> 
                        <span id="zenith-verdict" style="color: var(--red); font-weight: bold;">Calculating...</span>
                    </div>

                    <?php if (isset($_SESSION['curr_user'])): ?>
                        <form action="backend.php" method="POST" class="rev-form">
                            <input type="hidden" name="action" value="add_rev">
                            <input type="hidden" name="aid" class="form-aid">
                            
                            <select name="tier_score" required>
                                <option value="" disabled selected>Rate it...</option>
                                <option value="5">🐐 Peak Fiction</option>
                                <option value="4">🔥 Really Good</option>
                                <option value="3">👍 One-Time Watch</option>
                                <option value="2">🍿 Just a Timepass</option>
                                <option value="1">💀 Why Does This Exist?</option>
                            </select>

                            <textarea name="rev_txt" placeholder="Write something..." required></textarea>
                            
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label style="font-size:0.9rem; cursor:pointer;">
                                    <input type="checkbox" name="is_spoiler" value="1"> ⚠️ Contains Spoilers
                                </label>
                                <button class="btn main-btn">Post</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="color:grey; font-size:12px;">Login to post reviews.</p>
                    <?php endif; ?>
                    
                    <div id="rev-list" style="margin-top:20px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="auth-modal" class="modal">
        <div class="modal-box auth-box">
            <span class="close" onclick="hideModal('auth-modal')">&times;</span>
            
            <div id="f-login">
                <h2>Login</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
                        <div class="error-msg">Bad login, try again.</div>
                    <?php endif; ?>
                    <input type="text" name="user" placeholder="Username" value="<?= $_COOKIE['auth_token'] ?? '' ?>" required>
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-signup')">Need an account?</span></div>
            </div>

            <div id="f-signup" style="display:none;">
                <h2>Register</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="text" name="phone" placeholder="Phone" required>
                    <input type="text" name="otp" placeholder="OTP (type 1234)" required>
                    <input type="text" name="user" placeholder="Username" required>
                    
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'taken'): ?>
                        <div class="error-msg">Username taken bro.</div>
                    <?php endif; ?>
                    
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Sign Up</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-login')">Back to login</span></div>
            </div>
        </div>
    </div>

    <div id="welcome-popup" class="toast"></div>
    <script src="app.js"></script>
</body>
</html><?php
                if (empty($lists[$k])) echo "<p style='color:grey; padding-left:20px;'>Empty</p>";
                foreach ($lists[$k] as $a): 
                ?>
                    <div class="card" onclick="loadAnime(<?= $a['id'] ?>)">
                        <img src="<?= $a['img'] ?>">
                        <h4><?= htmlspecialchars($a['title']) ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
    </div>

    <div id="tab-admin" class="tab">
        <h2 class="sec-title">All Reviews (Admin)</h2>
        <div class="card-box">
            <?php
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
                $chk = true;
                foreach ($_SESSION['reviews'] as $aid => $revs) {
                    foreach ($revs as $r) {
                        $chk = false;
                        echo "<div class='rev-box'>
                                <b>{$r['user']}</b> (@{$r['username']}) - Anime: $aid<br>{$r['txt']}
                                <form action='backend.php' method='POST' style='float:right; margin-top:-25px;'>
                                    <input type='hidden' name='action' value='del_rev'>
                                    <input type='hidden' name='aid' value='$aid'>
                                    <input type='hidden' name='rid' value='{$r['id']}'>
                                    <button class='btn-del'>Del</button>
                                </form>
                              </div>";
                    }
                }
                if ($chk) echo "<p>No reviews found.</p>";
            }
            ?>
        </div>
    </div>

    <div id="anime-modal" class="modal">
        <div class="modal-box">
            <span class="close" onclick="hideModal('anime-modal')">&times;</span>
            <div id="loading">Loading...</div>
            
            <div id="anime-data" style="display:none;">
                <div class="split">
                    <img id="m-img" src="">
                    <div class="info">
                        <h2 id="m-title">Title</h2>
                        <div class="tags">
                            <span id="m-score">⭐</span>
                            <span id="m-eps"></span>
                        </div>
                        <p id="m-desc"></p>
                        
                        <?php if (isset($_SESSION['curr_user'])): ?>
                            <div class="btn-group">
                                <?php foreach(['later' => 'Watch Later', 'watched' => 'Watched', 'favs' => '+ Favs'] as $k => $v): ?>
                                <form action="backend.php" method="POST">
                                    <input type="hidden" name="action" value="add_list">
                                    <input type="hidden" name="type" value="<?= $k ?>">
                                    <input type="hidden" name="aid" class="form-aid">
                                    <input type="hidden" name="title" class="form-title">
                                    <input type="hidden" name="img" class="form-img">
                                    <button class="btn alt-btn"><?= $v ?></button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:red; font-size:14px;">Login to save anime.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="reviews-section">
                    <h3>Reviews</h3>
                    <?php if (isset($_SESSION['curr_user'])): ?>
                        <form action="backend.php" method="POST" class="rev-form">
                            <input type="hidden" name="action" value="add_rev">
                            <input type="hidden" name="aid" class="form-aid">
                            <textarea name="rev_txt" placeholder="Write something..." required></textarea>
                            <button class="btn main-btn">Post</button>
                        </form>
                    <?php else: ?>
                        <p style="color:grey; font-size:12px;">Login to post reviews.</p>
                    <?php endif; ?>
                    <div id="rev-list"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="auth-modal" class="modal">
        <div class="modal-box auth-box">
            <span class="close" onclick="hideModal('auth-modal')">&times;</span>
            
            <div id="f-login">
                <h2>Login</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
                        <div style="color:red; font-size:12px; margin-bottom:10px;">Bad login, try again.</div>
                    <?php endif; ?>
                    <input type="text" name="user" placeholder="Username" value="<?= $_COOKIE['auth_token'] ?? '' ?>" required>
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-signup')">Need an account?</span></div>
            </div>

            <div id="f-signup" style="display:none;">
                <h2>Register</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="text" name="phone" placeholder="Phone" required>
                    <input type="text" name="otp" placeholder="OTP (type 1234)" required>
                    <input type="text" name="user" placeholder="Username" required>
                    
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'taken'): ?>
                        <div style="color:red; font-size:12px; margin-bottom:10px;">Username taken bro.</div>
                    <?php endif; ?>
                    
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Sign Up</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-login')">Back to login</span></div>
            </div>
        </div>
    </div>

    <div id="welcome-popup" class="toast"></div>
    <script src="app.js"></script>
</body>
</html>
