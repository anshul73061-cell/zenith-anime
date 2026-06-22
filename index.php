<?php 
session_start(); 
require_once('backend.php');

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
        const activeUser = "<?= $u ?? '' ?>";
        var revDB = <?= json_encode($_SESSION['db']['reviews'] ?? new stdClass()) ?>;
    </script>

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
            <input type="text" id="searchBar" placeholder="Search AniList..." onkeyup="if(event.key === 'Enter') doSearch()">
            <button class="btn alt-btn" onclick="getRandom()" title="I'm feeling lucky">🎲</button>
        </div>
        
        <div class="user-area">
            <?php if ($u): ?>
                <div class="bell-container" onclick="toggleInbox()">
                    🔔 <?php if(count($my_inbox) > 0) echo "<span class='badge'>".count($my_inbox)."</span>"; ?>
                    <div id="inbox-drop" class="inbox-drop">
                        <?php 
                        if(empty($my_inbox)) echo "<p style='padding:10px; color:grey; text-align:center;'>No alerts.</p>";
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

    <div id="quote-banner" class="quote-banner">"Loading wisdom..." - Zenith</div>

    <div class="ticker-wrap">
        <div class="ticker-move">
            <?php 
            if(empty($_SESSION['db']['activity'])) echo "<div class='ticker-item'>No recent activity. Do something!</div>";
            foreach($_SESSION['db']['activity'] as $act) echo "<div class='ticker-item'>$act</div>";
            ?>
        </div>
    </div>

    <div id="tab-home" class="tab active" style="display:flex; padding: 2rem;">
        <div style="flex: 1;">
            <h3 class="sec-title" id="main-header">🔥 Trending Now</h3>
            <div class="grid" id="pop-grid"></div>
            <h3 class="sec-title">Highly Anticipated Releases</h3>
            <div class="grid" id="upcoming-grid"></div>
        </div>
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

    <div id="tab-col" class="tab">
        <h2 class="sec-title">My Collection</h2>
        <?php 
        if ($u): 
            $lists = $_SESSION['db']['collections'][$u];
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
                        <?php if($k == 'watching'): ?>
                            <div class="tracker" style="background:#222; font-size:0.8rem; padding:5px; display:flex; justify-content:space-between; align-items:center;">
                                <span>Eps: <span id="ep-cnt-<?= $a['id'] ?>"><?= $a['eps'] ?></span></span>
                                <div>
                                    <button class="ep-btn" onclick="event.stopPropagation(); updateEp(<?= $a['id'] ?>, 'sub')">-</button>
                                    <button class="ep-btn" onclick="event.stopPropagation(); updateEp(<?= $a['id'] ?>, 'add')">+</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div id="tab-settings" class="tab">
        <h2 class="sec-title">Settings</h2>
        <div class="card-box">
            <?php if ($u): ?>
                <div style="display:flex; gap:20px; align-items:center;">
                    <div style="font-size:4rem; background:var(--bg); border-radius:50%; width:100px; height:100px; text-align:center; line-height:100px; border:2px solid var(--border);"><?= $my_avatar ?></div>
                    <div>
                        <p><b>Name:</b> <?= htmlspecialchars($_SESSION['db']['users'][$u]['name']) ?></p>
                        <p><b>Username:</b> @<?= htmlspecialchars($u) ?></p>
                        <p style="color:var(--red); font-weight:bold;"><?= $my_title ?></p>
                    </div>
                </div>
                <hr style="border-color: var(--border); margin: 1.5rem 0;">
                <h3>Change Avatar</h3>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                    <?php 
                    $emojis = ['🦊','🐼','🐯','🐸','🐵','🦉','🐺','🦄','🐉','👽','💀','🤖','👺','👾', '👤'];
                    foreach($emojis as $e): ?>
                        <div class="emoji-box" onclick="changeAvatar('<?= $e ?>')"><?= $e ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
                        <p id="m-desc" style="max-height: 150px; overflow-y: auto; padding-right: 10px;"></p>
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
                    <div id="zenith-verdict-box" style="display: none;">
                        <div class="verdict-container">
                            <div class="verdict-gauge">
                                <svg viewBox="0 0 100 50" class="half-donut">
                                    <path class="gauge-bg" d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#222" stroke-width="10" stroke-linecap="round"/>
                                    <path id="gauge-fill" class="gauge-fill" d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="var(--red)" stroke-width="10" stroke-linecap="round" stroke-dasharray="125.6" stroke-dashoffset="125.6" />
                                </svg>
                                <div class="gauge-text">
                                    <h3 id="verdict-pct">0%</h3>
                                    <p id="verdict-total">0 Votes</p>
                                </div>
                            </div>
                            <p id="verdict-tier">No Verdict</p>
                            <div class="verdict-legend" id="verdict-breakdown"></div>
                        </div>
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
                
                <div id="mutual-box" style="background:var(--bg); border:1px solid var(--border); padding:15px; border-radius:8px; text-align:left; display:none; margin-bottom:15px;">
                    <h4 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:5px;">📡 Mutual Watch Later Radar</h4>
                    <ul id="mutual-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0;"></ul>
                </div>
                <div id="top3-box" style="background:var(--bg); border:1px solid gold; padding:15px; border-radius:8px; text-align:left; display:none;">
                    <h4 style="margin-top:0; color:gold; border-bottom:1px solid var(--border); padding-bottom:5px;">🏆 Top 3 Podium</h4>
                    <ul id="top3-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0; color:white;"></ul>
                </div>
            </div>
        </div>
    </div>

    <div id="auth-modal" class="modal">
        <div class="modal-box auth-box" style="max-width: 400px; text-align: center;">
            <span class="close" onclick="hideModal('auth-modal')">&times;</span>
            <div id="f-login">
                <h2>Login</h2>
                <form action="backend.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="user" placeholder="Username" required autocomplete="off">
                    <input type="password" name="pass" placeholder="Password" required autocomplete="new-password">
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem; color: grey;">Don't have an account? <span style="color: var(--red); font-weight: bold; cursor: pointer;" onclick="toggleAuth('register')">Register here</span></p>
            </div>
            <div id="f-register" style="display: none;">
                <h2>Create Account</h2>
                <form action="backend.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="name" placeholder="Display Name (e.g. Naruto Fan)" required autocomplete="off">
                    <input type="text" name="user" placeholder="Username" required autocomplete="off">
                    <input type="password" name="pass" placeholder="Password" required autocomplete="new-password">
                    <button class="btn main-btn block-btn">Join Zenith</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem; color: grey;">Already have an account? <span style="color: var(--red); font-weight: bold; cursor: pointer;" onclick="toggleAuth('login')">Login here</span></p>
            </div>
        </div>
    </div>

    <script src="app.js?v=3"></script>
</body>
</html>
            $lists = $_SESSION['db']['collections'][$u];
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

                        <?php if($k == 'watching'): ?>
                            <div class="tracker" style="background:#222; font-size:0.8rem; padding:5px; display:flex; justify-content:space-between; align-items:center;">
                                <span>Eps: <span id="ep-cnt-<?= $a['id'] ?>"><?= $a['eps'] ?></span></span>
                                <div>
                                    <button class="ep-btn" onclick="event.stopPropagation(); updateEp(<?= $a['id'] ?>, 'sub')">-</button>
                                    <button class="ep-btn" onclick="event.stopPropagation(); updateEp(<?= $a['id'] ?>, 'add')">+</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div id="tab-settings" class="tab">
        <h2 class="sec-title">Settings</h2>
        <div class="card-box">
            <?php if ($u): ?>
                <div style="display:flex; gap:20px; align-items:center;">
                    <div style="font-size:4rem; background:var(--bg); border-radius:50%; width:100px; height:100px; text-align:center; line-height:100px; border:2px solid var(--border);">
                        <?= $my_avatar ?>
                    </div>
                    <div>
                        <p><b>Name:</b> <?= htmlspecialchars($_SESSION['db']['users'][$u]['name']) ?></p>
                        <p><b>Username:</b> @<?= htmlspecialchars($u) ?></p>
                        <p style="color:var(--red); font-weight:bold;"><?= $my_title ?></p>
                    </div>
                </div>
                
                <hr style="border-color: var(--border); margin: 1.5rem 0;">
                
                <h3>Change Avatar</h3>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                    <?php 
                    $emojis = ['🦊','🐼','🐯','🐸','🐵','🦉','🐺','🦄','🐉','👽','💀','🤖','👺','👾', '👤'];
                    foreach($emojis as $e): ?>
                        <div class="emoji-box" onclick="changeAvatar('<?= $e ?>')"><?= $e ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
                
                <div id="mutual-box" style="background:var(--bg); border:1px solid var(--border); padding:15px; border-radius:8px; text-align:left; display:none; margin-bottom:15px;">
                    <h4 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:5px;">📡 Mutual Watch Later Radar</h4>
                    <ul id="mutual-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0;"></ul>
                </div>

                <div id="top3-box" style="background:var(--bg); border:1px solid gold; padding:15px; border-radius:8px; text-align:left; display:none;">
                    <h4 style="margin-top:0; color:gold; border-bottom:1px solid var(--border); padding-bottom:5px;">🏆 Top 3 Podium</h4>
                    <ul id="top3-list" style="padding-left:20px; font-size:0.9rem; margin-bottom:0; color:white;"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- AUTH MODAL (Login / Register) -->
    <div id="auth-modal" class="modal">
        <div class="modal-box auth-box" style="max-width: 400px; text-align: center;">
            <span class="close" onclick="hideModal('auth-modal')">&times;</span>
            
            <!-- LOGIN FORM -->
            <div id="f-login">
                <h2>Login</h2>
                <!-- Added autocomplete="off" -->
                <form action="backend.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="user" placeholder="Username" required autocomplete="off">
                    <!-- The new-password trick stops Chrome from auto-filling -->
                    <input type="password" name="pass" placeholder="Password" required autocomplete="new-password">
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem; color: grey;">
                    Don't have an account? <span style="color: var(--red); font-weight: bold; cursor: pointer;" onclick="toggleAuth('register')">Register here</span>
                </p>
            </div>

            <!-- REGISTER FORM -->
            <div id="f-register" style="display: none;">
                <h2>Create Account</h2>
                <form action="backend.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="name" placeholder="Display Name (e.g. Naruto Fan)" required autocomplete="off">
                    <input type="text" name="user" placeholder="Username" required autocomplete="off">
                    <input type="password" name="pass" placeholder="Password" required autocomplete="new-password">
                    <button class="btn main-btn block-btn">Join Zenith</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem; color: grey;">
                    Already have an account? <span style="color: var(--red); font-weight: bold; cursor: pointer;" onclick="toggleAuth('login')">Login here</span>
                </p>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
