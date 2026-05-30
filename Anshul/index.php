<?php 
session_start(); 
$is_light = isset($_COOKIE['site_theme']) && $_COOKIE['site_theme'] == 'light';
$theme_class = $is_light ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Zenith Anime</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?= $theme_class ?>">
    
    <script>
        const activeUser = "<?= $_SESSION['curr_user'] ?? '' ?>";
        const activeName = "<?= addslashes($_SESSION['curr_name'] ?? '') ?>";
        const userRole = "<?= $_SESSION['role'] ?? '' ?>";
        // using json_encode so quotes don't break the js parser
        let revDB = <?= json_encode($_SESSION['reviews'] ?? new stdClass()) ?>;
    </script>

    <nav class="nav">
        <div class="logo" onclick="switchTab('home')">Zenith<span>Anime</span></div>
        <input type="text" id="searchBar" placeholder="Search anime..." onkeyup="if(event.key === 'Enter') doSearch()">
        
        <div class="user-area">
            <?php if (isset($_SESSION['curr_user'])): ?>
                <span class="user-name" onclick="switchTab('settings')">Hi, <?= htmlspecialchars($_SESSION['curr_name']) ?> ⚙️</span>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <button class="btn main-btn" onclick="switchTab('admin')">Admin</button>
                <?php else: ?>
                    <button class="btn main-btn" onclick="switchTab('col')">My Collection</button>
                <?php endif; ?>
                
                <form action="backend.php" method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn alt-btn">Logout</button>
                </form>
            <?php else: ?>
                <button class="btn main-btn" onclick="showModal('auth-modal')">Login</button>
            <?php endif; ?>
        </div>
    </nav>

    <div id="tab-settings" class="tab">
        <h2 class="sec-title">Settings</h2>
        <div class="card-box">
            <?php if (isset($_SESSION['curr_user'])): ?>
                <p><b>Name:</b> <?= htmlspecialchars($_SESSION['curr_name']) ?></p>
                <p><b>Username:</b> @<?= htmlspecialchars($_SESSION['curr_user']) ?></p>
                <hr style="border-color: var(--border); margin: 1.5rem 0;">
                <button class="btn main-btn" onclick="toggleTheme()">Switch Dark/Light Mode</button>
            <?php endif; ?>
        </div>
    </div>

    <div id="tab-home" class="tab active">
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
            $categories = ['later' => 'Watch Later', 'watched' => 'Watched', 'favs' => 'Favorites'];
            
            foreach ($categories as $key => $title):
        ?>
            <h3><?= $title ?></h3>
            <div class="grid">
                <?php 
                if (empty($lists[$key])) echo "<p class='mute'>Nothing here yet.</p>";
                foreach ($lists[$key] as $a): 
                ?>
                    <div class="card" onclick="loadAnime(<?= $a['id'] ?>)">
                        <img src="<?= $a['img'] ?>">
                        <h4><?= htmlspecialchars($a['title']) ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; endif; ?>
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
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="reviews-section">
                    <h3>Reviews</h3>
                    <?php if (isset($_SESSION['curr_user'])): ?>
                        <form action="backend.php" method="POST" class="rev-form">
                            <input type="hidden" name="action" value="add_rev">
                            <input type="hidden" name="aid" class="form-aid">
                            <textarea name="rev_txt" placeholder="Write a review..." required></textarea>
                            <button class="btn main-btn">Post</button>
                        </form>
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
                    <input type="text" name="user" placeholder="Username" value="<?= $_COOKIE['remembered_user'] ?? '' ?>" required>
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Enter</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-signup')">Create Account</span></div>
            </div>

            <div id="f-signup" style="display:none;">
                <h2>Sign Up</h2>
                <form action="backend.php" method="POST">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="text" name="phone" placeholder="Phone Number" required>
                    <input type="text" name="otp" placeholder="OTP (type 1234)" required>
                    <input type="text" name="user" placeholder="Username" required>
                    
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'taken'): ?>
                        <div class="error-msg">Username taken, try another.</div>
                    <?php endif; ?>
                    
                    <input type="password" name="pass" placeholder="Password" required>
                    <button class="btn main-btn block-btn">Register</button>
                </form>
                <div class="links"><span onclick="swapAuth('f-login')">Back to Login</span></div>
            </div>
        </div>
    </div>

    <div id="welcome-popup" class="toast"></div>

    <script src="app.js"></script>
</body>
</html>