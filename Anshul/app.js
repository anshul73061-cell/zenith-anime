const API = 'https://api.jikan.moe/v4';
const sleep = ms => new Promise(r => setTimeout(r, ms));

let currAnime = null; 
let viewedProfile = ""; 

document.addEventListener('DOMContentLoaded', async () => {
    fetchQuote();
    // Only load these if we are on index.php
    if(document.getElementById('pop-grid')) {
        await getGrid('/top/anime?filter=bypopularity', 'pop-grid', 12);
        await sleep(400); // polite rate limit pause
        await getGrid('/seasons/upcoming', 'upcoming-grid', 12);
        
        // tab redirection support
        let url = new URLSearchParams(window.location.search);
        if (url.get('tab') == 'col') switchTab('col');
    }
});

async function fetchQuote() {
    let qBox = document.getElementById('quote-banner');
    if(!qBox) return;
    try {
        let res = await fetch('https://animechan.xyz/api/random');
        let d = await res.json();
        qBox.innerHTML = `"${d.quote}" - <span style="color:var(--red);">${d.character}</span>`;
    } catch(err) {
        // fallback so UI doesn't look broken if API fails
        qBox.innerHTML = `"A dropout will beat a genius through hard work." - <span style="color:var(--red);">Rock Lee</span>`;
    }
}

async function getGrid(endpoint, elId, limit = 12) {
    try {
        let res = await fetch(`${API}${endpoint}&limit=${limit}`);
        let d = await res.json();
        let html = '';
        d.data.forEach(a => {
            let t = a.title_english || a.title;
            html += `<div class="card" onclick="loadAnime(${a.mal_id})"><img src="${a.images.jpg.image_url}"><h4>${t}</h4></div>`;
        });
        document.getElementById(elId).innerHTML = html;
    } catch(e) { console.log(e); }
}

async function doSearch() {
    let q = document.getElementById('searchBar').value;
    if(q == "") return;
    document.getElementById('main-header').innerText = `Search Results for: "${q}"`;
    document.getElementById('pop-grid').innerHTML = '<p>Searching...</p>';
    getGrid(`/anime?q=${q}&order_by=score&sort=desc`, 'pop-grid', 18);
}

async function getRandom() {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';
    let res = await fetch(`${API}/random/anime`);
    let d = await res.json();
    renderModalData(d.data);
}

async function loadAnime(id) {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';
    if(document.getElementById('trailer-container')) document.getElementById('trailer-container').style.display = 'none';

    let res = await fetch(`${API}/anime/${id}`);
    let d = await res.json();
    renderModalData(d.data);
}

function renderModalData(a) {
    currAnime = a; 
    let engTitle = a.title_english || a.title;

    document.getElementById('m-title').innerText = engTitle;
    document.getElementById('m-score').innerText = `⭐ ${a.score || 'NA'}`;
    document.getElementById('m-eps').innerText = `${a.episodes || '?'} Eps`;
    document.getElementById('m-desc').innerText = a.synopsis || "No desc.";
    document.getElementById('m-img').src = a.images.jpg.large_image_url;

    let tBtn = document.getElementById('trailer-btn');
    if(a.trailer.youtube_id) tBtn.style.display = 'inline-block';
    else tBtn.style.display = 'none';

    renderReviews();

    document.getElementById('loading').style.display = 'none';
    document.getElementById('anime-data').style.display = 'block';
}

function playTrailer() {
    let tc = document.getElementById('trailer-container');
    tc.style.display = 'block';
    tc.innerHTML = `<iframe width="100%" height="250" src="https://www.youtube.com/embed/${currAnime.trailer.youtube_id}?autoplay=1" frameborder="0" allowfullscreen></iframe>`;
}

// ---- AJAX DATABASE ACTIONS ---- //

async function saveToList(type) {
    if(!activeUser) return alert("Log in first!");
    let dropEp = 0;
    
    if(type === 'dropped') {
        dropEp = prompt("Oof. What episode did you rage-quit on?");
        if(!dropEp) return;
    }
    
    await fetch('backend.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add_list', type: type, aid: currAnime.mal_id, 
            title: currAnime.title_english || currAnime.title, 
            img: currAnime.images.jpg.image_url, drop_ep: dropEp
        })
    });
    alert(type === 'top3' ? 'Pinned to Podium!' : `Saved to ${type}!`);
}

async function postReview() {
    if(!activeUser) return alert("Log in first!");
    let t = document.getElementById('tier_score').value;
    let txt = document.getElementById('rev_txt').value;
    let sp = document.getElementById('is_spoiler').checked;
    
    if(!t || !txt) return alert("Fill out the score and text!");

    let res = await fetch('backend.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'add_rev', aid: currAnime.mal_id, anime_title: currAnime.title_english || currAnime.title,
            tier_score: t, rev_txt: txt, is_spoiler: sp
        })
    });
    let d = await res.json();
    if(d.status === 'ok') {
        revDB[currAnime.mal_id] = d.reviews; 
        renderReviews(); 
        document.getElementById('rev_txt').value = ''; 
    }
}

async function wTake(rid) {
    if(!activeUser) return alert("Log in to hype this up!");
    let res = await fetch('backend.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'w_take', aid: currAnime.mal_id, rid: rid })
    });
    let d = await res.json();
    revDB[currAnime.mal_id] = d.reviews;
    renderReviews(); // rerender to pin the top review
}

function renderReviews() {
    let rHtml = '';
    let reviews = revDB[currAnime?.mal_id] || [];
    let totalScore = 0; let scoreCount = 0;
    const tierLabels = ["No votes", "💀 Why Does This Exist?", "🍿 Just a Timepass", "👍 One-Time Watch", "🔥 Really Good", "🐐 Peak Fiction"];
    
    if (reviews.length == 0) {
        document.getElementById('zenith-verdict').innerText = tierLabels[0];
        document.getElementById('rev-list').innerHTML = '<p style="color:grey;">No reviews yet.</p>';
        return;
    } 

    // Sort to float the most W-Takes to the top
    reviews.sort((a, b) => (b.wtakes?.length || 0) - (a.wtakes?.length || 0));

    reviews.forEach((r, index) => {
        if (r.score) { totalScore += parseInt(r.score); scoreCount++; }
        
        let userVote = r.score ? `<span style="color:var(--red); font-size:0.8rem;">[${tierLabels[r.score]}]</span>` : '';
        let reviewBody = r.spoiler ? `<div class="spoiler-text" onclick="this.classList.toggle('revealed')">⚠️ SPOILER (Click to read): ${r.txt}</div>` : r.txt;
        
        let wCount = r.wtakes?.length || 0;
        let isTop = (index === 0 && wCount > 0) ? 'top-review' : '';
        let hasMyW = r.wtakes?.includes(activeUser) ? 'btn-red' : '';

        rHtml += `<div class="rev-box ${isTop}">
                    <span style="font-size:1.2rem;">${r.avatar || '👤'}</span> 
                    <b onclick="openProfile('${r.username}')" style="cursor:pointer; color:var(--red);">@${r.username}</b> 
                    <span style="color:gold; font-size:0.8rem;">${r.title || ''}</span> ${userVote}
                    <br><br>${reviewBody}
                    <div style="margin-top:10px; border-top:1px solid var(--border); padding-top:5px;">
                        <button class="btn alt-btn ${hasMyW}" style="font-size:0.8rem; padding:2px 10px;" onclick="wTake(${r.id})">🔥 W Take (${wCount})</button>
                    </div>
                  </div>`;
    });
    
    if (scoreCount > 0) document.getElementById('zenith-verdict').innerText = tierLabels[Math.round(totalScore / scoreCount)];
    document.getElementById('rev-list').innerHTML = rHtml;
}

// ---- SOCIAL PROFILE STUFF ---- //

async function openProfile(user) {
    viewedProfile = user;
    showModal('user-modal');
    document.getElementById('u-prof-loading').style.display = 'block';
    document.getElementById('u-prof-data').style.display = 'none';

    let res = await fetch('backend.php', {
        method: 'POST', body: JSON.stringify({ action: 'get_profile', target: user })
    });
    let d = await res.json();
    
    document.getElementById('u-avatar').innerText = d.user.avatar || '👤';
    document.getElementById('u-name').innerText = user;
    document.getElementById('u-title').innerText = d.title;
    document.getElementById('u-followers').innerText = d.followers;
    
    let fb = document.getElementById('follow-btn');
    if(user === activeUser || !activeUser) fb.style.display = 'none';
    else {
        fb.style.display = 'inline-block';
        fb.innerText = d.is_following ? 'Unfollow' : 'Follow';
    }

    // Mutual Watch Later Radar
    let mBox = document.getElementById('mutual-box');
    let mList = document.getElementById('mutual-list');
    mList.innerHTML = '';
    if(d.mutual.length > 0) {
        mBox.style.display = 'block';
        d.mutual.forEach(show => {
            mList.innerHTML += `<li style="cursor:pointer; color:var(--red);" onclick="loadAnime(${show.id})">${show.title}</li>`;
        });
    } else { mBox.style.display = 'none'; }

    // Top 3 Podium
    let tBox = document.getElementById('top3-box');
    let tList = document.getElementById('top3-list');
    tList.innerHTML = '';
    if(d.top3 && d.top3.length > 0) {
        tBox.style.display = 'block';
        d.top3.forEach(show => {
            tList.innerHTML += `<li style="cursor:pointer; font-weight:bold;" onclick="loadAnime(${show.id})">🏅 ${show.title}</li>`;
        });
    } else { tBox.style.display = 'none'; }

    document.getElementById('u-prof-loading').style.display = 'none';
    document.getElementById('u-prof-data').style.display = 'block';
}

async function toggleFollow() {
    await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'toggle_follow', target: viewedProfile }) });
    openProfile(viewedProfile); 
}

function toggleInbox() { document.getElementById('inbox-drop').classList.toggle('show'); }

async function clearInbox() {
    await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'clear_inbox' }) });
    window.location.reload(); 
}

async function voteVs(side) {
    let res = await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'vote_vs', side: side }) });
    let d = await res.json();
    let total = parseInt(d.v1) + parseInt(d.v2);
    document.getElementById('v1-pct').innerText = Math.round((d.v1/total)*100) + '%';
    document.getElementById('v2-pct').innerText = Math.round((d.v2/total)*100) + '%';
}

function switchTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
}
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { 
    document.getElementById(id).style.display = 'none'; 
    if(document.getElementById('trailer-container')) document.getElementById('trailer-container').innerHTML = ''; // stops video playing in bg
}
