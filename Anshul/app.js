const API = 'https://api.jikan.moe/v4';

// jikan api rate limits if you hit it too fast
// just sleeping the thread to fix it lol
const sleep = ms => new Promise(r => setTimeout(r, ms));

document.addEventListener('DOMContentLoaded', async () => {
    // console.log("running fetch...");
    
    await getHero();
    await sleep(400); 
    await getGrid('/top/anime?filter=bypopularity', 'pop-grid', 12);
    await sleep(400);
    await getGrid('/top/anime?filter=favorite', 'top-grid', 6);
    await sleep(400);
    await getGrid('/seasons/now', 'recent-grid', 6);

    // check url for php redirects
    var url = new URLSearchParams(window.location.search);
    
    if (url.get('open')) {
        loadAnime(url.get('open'));
    }
    
    if (url.get('error') === 'taken') {
        showModal('auth-modal');
        swapAuth('f-signup');
    }
    
    if (url.get('welcome') && activeName) {
        showToast(`Welcome back, ${activeName}!`);
        // clear url so it doesn't pop up again
        window.history.replaceState({}, document.title, "index.php"); 
    }
});

function showToast(msg) {
    let t = document.getElementById('welcome-popup');
    t.innerText = msg;
    t.classList.add('show');
    // hacky way to hide it
    setTimeout(function() { t.classList.remove('show'); }, 3500); 
}

async function getHero() {
    try {
        let res = await fetch(`${API}/seasons/now?limit=8`);
        let d = await res.json();
        let html = '';
        
        // render twice for css scroll trick
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
    } catch(err) {
        console.error("api ded", err);
    }
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

async function loadAnime(id) {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';

    let res = await fetch(`${API}/anime/${id}`);
    let d = await res.json();
    let a = d.data;
    
    // console.log("clicked anime data:", a);

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

    // render revs
    let rHtml = '';
    let reviews = revDB[a.mal_id] || [];
    
    if (reviews.length == 0) {
        rHtml = '<p style="color:grey; font-size:14px;">No reviews yet.</p>';
    } else {
        reviews.forEach(r => {
            let delBtn = '';
            if (activeUser == r.username || userRole == 'admin') {
                delBtn = `
                <form action="backend.php" method="POST" class="del-form">
                    <input type="hidden" name="action" value="del_rev">
                    <input type="hidden" name="aid" value="${a.mal_id}">
                    <input type="hidden" name="rid" value="${r.id}">
                    <button class="btn-del">Delete</button>
                </form>`;
            }
            rHtml += `<div class="rev-box"><b>${r.user}</b> <span style="color:grey; font-size:12px;">(@${r.username})</span><br>${r.txt} ${delBtn}</div>`;
        });
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
