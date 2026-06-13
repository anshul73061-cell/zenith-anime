const API = 'https://api.jikan.moe/v4';

const sleep = ms => new Promise(r => setTimeout(r, ms));

document.addEventListener('DOMContentLoaded', async () => {
    
    await getHero();
    await sleep(400); 
    await getGrid('/top/anime?filter=bypopularity', 'pop-grid', 12);
    await sleep(400);
    await getGrid('/top/anime?filter=favorite', 'top-grid', 6);
    await sleep(400);
    await getGrid('/seasons/now', 'recent-grid', 6);

    // handles redirects
    var url = new URLSearchParams(window.location.search);
    
    if (url.get('open')) loadAnime(url.get('open'));
    if (url.get('tab')) switchTab(url.get('tab')); // switch to col tab after +/- click
    
    if (url.get('error') === 'taken') {
        showModal('auth-modal');
        swapAuth('f-signup');
    }
    
    if (url.get('welcome') && activeName) {
        showToast(`Welcome back, ${activeName}!`);
        window.history.replaceState({}, document.title, "index.php"); 
    }
});

function showToast(msg) {
    let t = document.getElementById('welcome-popup');
    t.innerText = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3500); 
}

async function getHero() {
    try {
        let res = await fetch(`${API}/seasons/now?limit=8`);
        let d = await res.json();
        let html = '';
        
        for(let i=0; i<2; i++) {
            d.data.forEach(a => {
                let t = a.title_english || a.title; 
                html += `<div class="slide-item" onclick="loadAnime(${a.mal_id})">
                            <img src="${a.images.jpg.large_image_url}">
                            <div class="title">${t}</div>
                         </div>`;
            });
        }
        document.getElementById('hero-track').innerHTML = html;
    } catch(err) { console.error("api ded", err); }
}

async function getGrid(endpoint, elId, limit = 12) {
    let res = await fetch(`${API}${endpoint}&limit=${limit}`);
    let d = await res.json();
    
    let html = '';
    d.data.forEach(a => {
        let t = a.title_english || a.title;
        html += `
        <div class="card" onclick="loadAnime(${a.mal_id})">
            <img src="${a.images.jpg.image_url}">
            <h4>${t}</h4>
        </div>`;
    });
    document.getElementById(elId).innerHTML = html;
}

async function doSearch() {
    var q = document.getElementById('searchBar').value;
    if(q == "") return;
    
    switchTab('home');
    document.getElementById('pop-grid').innerHTML = '<p>Searching...</p>';
    getGrid(`/anime?q=${q}&order_by=score&sort=desc`, 'pop-grid', 18);
}

// I'm feeling lucky feature
async function getRandom() {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';
    
    let res = await fetch(`${API}/random/anime`);
    let d = await res.json();
    // reuse load logic
    renderModalData(d.data);
}

async function loadAnime(id) {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';

    let res = await fetch(`${API}/anime/${id}`);
    let d = await res.json();
    renderModalData(d.data);
}

function renderModalData(a) {
    let engTitle = a.title_english || a.title;

    document.getElementById('m-title').innerText = engTitle;
    document.getElementById('m-score').innerText = `⭐ ${a.score || 'NA'}`;
    document.getElementById('m-eps').innerText = `${a.episodes || '?'} Eps`;
    document.getElementById('m-desc').innerText = a.synopsis || "No desc.";
    document.getElementById('m-img').src = a.images.jpg.large_image_url;

    // fill form inputs
    document.querySelectorAll('.form-aid').forEach(i => i.value = a.mal_id);
    document.querySelectorAll('.form-title').forEach(i => i.value = engTitle);
    document.querySelectorAll('.form-img').forEach(i => i.value = a.images.jpg.image_url);

    // render revs & calculate Zenith Score
    let rHtml = '';
    let reviews = revDB[a.mal_id] || [];
    
    let totalScore = 0;
    let scoreCount = 0;
    const tierLabels = ["No votes yet", "💀 Why Does This Exist?", "🍿 Just a Timepass", "👍 One-Time Watch", "🔥 Really Good", "🐐 Peak Fiction"];
    
    if (reviews.length == 0) {
        rHtml = '<p style="color:grey; font-size:14px;">No reviews yet.</p>';
        document.getElementById('zenith-verdict').innerText = tierLabels[0];
    } else {
        reviews.forEach(r => {
            // math
            if (r.score) {
                totalScore += parseInt(r.score);
                scoreCount++;
            }

            let delBtn = '';
            if (activeUser == r.username || userRole == 'admin') {
                delBtn = `
                <form action="backend.php" method="POST" style="position:absolute; right:10px; top:10px;">
                    <input type="hidden" name="action" value="del_rev">
                    <input type="hidden" name="aid" value="${a.mal_id}">
                    <input type="hidden" name="rid" value="${r.id}">
                    <button class="btn-del">X</button>
                </form>`;
            }
            
            let userVote = r.score ? `<span style="color:var(--red); font-size:0.8rem;">[${tierLabels[r.score]}]</span>` : '';
            
            // wrap text in spoiler class if checked
            let reviewBody = r.spoiler ? `<div class="spoiler-text" onclick="this.classList.toggle('revealed')" title="Click to reveal spoiler">⚠️ SPOILER: ${r.txt}</div>` : r.txt;
            
            rHtml += `<div class="rev-box">
                        <span style="font-size:1.5rem; margin-right:5px;">${r.avatar || '👤'}</span> 
                        <b>${r.user}</b> <span style="color:grey; font-size:12px;">(@${r.username})</span> ${userVote}
                        <br><br>${reviewBody} ${delBtn}
                      </div>`;
        });
        
        // update top verdict
        if (scoreCount > 0) {
            let avg = Math.round(totalScore / scoreCount);
            document.getElementById('zenith-verdict').innerText = tierLabels[avg];
        } else {
            document.getElementById('zenith-verdict').innerText = tierLabels[0];
        }
    }
    document.getElementById('rev-list').innerHTML = rHtml;

    document.getElementById('loading').style.display = 'none';
    document.getElementById('anime-data').style.display = 'block';
}

function switchTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    window.scrollTo(0,0);
}

function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

function swapAuth(id) {
    document.getElementById('f-login').style.display = 'none';
    document.getElementById('f-signup').style.display = 'none';
    document.getElementById(id).style.display = 'block';
}

function toggleTheme() {
    let light = document.body.classList.toggle('light-mode');
    document.cookie = `site_theme=${light ? 'light' : 'dark'}; path=/; max-age=2592000`; // 30 days
}
