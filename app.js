const ANILIST_URL = 'https://graphql.anilist.co';
let currAnime = null; 
let viewedProfile = ""; 

// Fallback Database updated to match AniList structure
const fallbackAnime = [
    { id: 20, title: { english: "Naruto", romaji: "Naruto" }, averageScore: 80, episodes: 220, description: "Moments prior to Naruto Uzumaki's birth...", coverImage: { extraLarge: "https://cdn.myanimelist.net/images/anime/13/17405l.jpg", large: "https://cdn.myanimelist.net/images/anime/13/17405l.jpg" }, trailer: { id: "j2hiC9W8SVc", site: "youtube" } },
    { id: 153288, title: { english: "Solo Leveling", romaji: "Ore dake Level Up na Ken" }, averageScore: 83, episodes: 12, description: "Ten years ago, the Gate appeared...", coverImage: { extraLarge: "https://m.media-amazon.com/images/M/MV5BMTEzNGZkMWEtZjZhMS00ZDIwLWIxZjEtNThhOTExYjVkYmZkXkEyXkFqcGdeQXVyMTEzMTI1Mjk3._V1_FMjpg_UX1000_.jpg", large: "https://m.media-amazon.com/images/M/MV5BMTEzNGZkMWEtZjZhMS00ZDIwLWIxZjEtNThhOTExYjVkYmZkXkEyXkFqcGdeQXVyMTEzMTI1Mjk3._V1_FMjpg_UX1000_.jpg" }, trailer: { id: "s7Z_wN4E0Xg", site: "youtube" } }
];

document.addEventListener('DOMContentLoaded', async () => {
    fetchQuote();
    if(document.getElementById('pop-grid')) {
        await fetchTrending();
        await fetchUpcoming();
        let url = new URLSearchParams(window.location.search);
        if (url.get('tab') == 'col') switchTab('col');
    }
});

// The GraphQL Fetch Wrapper
async function fetchAniList(query, variables = {}) {
    let options = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ query: query, variables: variables })
    };
    let res = await fetch(ANILIST_URL, options);
    if (!res.ok) throw new Error('AniList API Error');
    return await res.json();
}

// Map GraphQL response to HTML grid
function renderGrid(mediaArray, elId) {
    let html = '';
    mediaArray.forEach(a => {
        let t = a.title.english || a.title.romaji;
        html += `<div class="card" onclick="loadAnime(${a.id})"><img src="${a.coverImage.large}"><h4>${t}</h4></div>`;
    });
    document.getElementById(elId).innerHTML = html;
}

// Queries
async function fetchTrending() {
    let query = `query { Page(page: 1, perPage: 12) { media(type: ANIME, sort: POPULARITY_DESC) { id title { english romaji } coverImage { large } } } }`;
    try {
        let data = await fetchAniList(query);
        renderGrid(data.data.Page.media, 'pop-grid');
    } catch(e) { renderGrid(fallbackAnime, 'pop-grid'); }
}

async function fetchUpcoming() {
    let query = `query { Page(page: 1, perPage: 12) { media(type: ANIME, status: NOT_YET_RELEASED, sort: POPULARITY_DESC) { id title { english romaji } coverImage { large } } } }`;
    try {
        let data = await fetchAniList(query);
        renderGrid(data.data.Page.media, 'upcoming-grid');
    } catch(e) { renderGrid(fallbackAnime, 'upcoming-grid'); }
}

async function doSearch() {
    let q = document.getElementById('searchBar').value;
    if(q == "") return;
    document.getElementById('main-header').innerText = `Search Results for: "${q}"`;
    document.getElementById('pop-grid').innerHTML = '<p>Searching...</p>';
    
    let query = `query($search: String) { Page(page: 1, perPage: 18) { media(type: ANIME, search: $search, sort: SEARCH_MATCH) { id title { english romaji } coverImage { large } } } }`;
    try {
        let data = await fetchAniList(query, { search: q });
        renderGrid(data.data.Page.media, 'pop-grid');
    } catch(e) { document.getElementById('pop-grid').innerHTML = '<p style="color:var(--red);">Search failed. API issue.</p>'; }
}

async function runExplore() {
    let el = document.getElementById('explore-grid');
    if(!el) return;
    el.innerHTML = '<p>Loading...</p>';
    
    let g = document.getElementById('ex-genre').value;
    let s = document.getElementById('ex-status').value;
    
    let query = `query($genre: String, $status: MediaStatus) { Page(page: 1, perPage: 24) { media(type: ANIME, genre: $genre, status: $status, sort: POPULARITY_DESC) { id title { english romaji } coverImage { large } } } }`;
    
    let vars = {};
    if(g !== "") vars.genre = g;
    if(s !== "") vars.status = s;
    
    try {
        let data = await fetchAniList(query, vars);
        renderGrid(data.data.Page.media, 'explore-grid');
    } catch(e) { el.innerHTML = '<p style="color:var(--red);">Explore failed. Try again later.</p>'; }
}

async function getRandom() {
    // AniList doesn't have a direct random endpoint, so we fetch a random page of popular anime
    let randomPage = Math.floor(Math.random() * 50) + 1;
    let query = `query { Page(page: ${randomPage}, perPage: 1) { media(type: ANIME, sort: POPULARITY_DESC) { id title { english romaji } coverImage { extraLarge } averageScore episodes description trailer { id site } } } }`;
    
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';
    
    try {
        let data = await fetchAniList(query);
        renderModalData(data.data.Page.media[0]);
    } catch(e) { renderModalData(fallbackAnime[0]); }
}

async function loadAnime(id) {
    showModal('anime-modal');
    document.getElementById('loading').style.display = 'block';
    document.getElementById('anime-data').style.display = 'none';
    if(document.getElementById('trailer-container')) document.getElementById('trailer-container').style.display = 'none';

    let query = `query($id: Int) { Media(id: $id, type: ANIME) { id title { english romaji } coverImage { extraLarge large } averageScore episodes description trailer { id site } } }`;
    
    try {
        let data = await fetchAniList(query, { id: id });
        renderModalData(data.data.Media);
    } catch(e) {
        let backup = fallbackAnime.find(a => a.id == id);
        if (backup) renderModalData(backup);
        else document.getElementById('loading').innerHTML = `<p style="color:var(--red);">API down. Fallback missing.</p>`;
    }
}

function renderModalData(a) {
    currAnime = a; 
    let engTitle = a.title.english || a.title.romaji;

    document.getElementById('m-title').innerText = engTitle;
    let visualScore = a.averageScore ? (a.averageScore / 10).toFixed(1) : 'NA';
    document.getElementById('m-score').innerText = `⭐ ${visualScore}`;
    document.getElementById('m-eps').innerText = `${a.episodes || '?'} Eps`;
    
    // AniList sends description with HTML tags, so we use innerHTML
    document.getElementById('m-desc').innerHTML = a.description || "No desc.";
    document.getElementById('m-img').src = a.coverImage.extraLarge || a.coverImage.large;

    let tBtn = document.getElementById('trailer-btn');
    if(a.trailer && a.trailer.site === 'youtube') tBtn.style.display = 'inline-block';
    else tBtn.style.display = 'none';

    renderReviews();

    document.getElementById('loading').style.display = 'none';
    document.getElementById('anime-data').style.display = 'block';
}

function playTrailer() {
    let tc = document.getElementById('trailer-container');
    tc.style.display = 'block';
    tc.innerHTML = `<iframe width="100%" height="250" src="https://www.youtube.com/embed/${currAnime.trailer.id}?autoplay=1" frameborder="0" allowfullscreen></iframe>`;
}

async function fetchQuote() {
    let qBox = document.getElementById('quote-banner');
    if(!qBox) return;
    try {
        let res = await fetch('https://animechan.xyz/api/random');
        let d = await res.json();
        qBox.innerHTML = `"${d.quote}" - <span style="color:var(--red);">${d.character}</span>`;
    } catch(err) {
        qBox.innerHTML = `"A dropout will beat a genius through hard work." - <span style="color:var(--red);">Rock Lee</span>`;
    }
}

// ---- AJAX ACTIONS & SOCIAL (UNCHANGED LOGIC, JUST USES a.id) ---- //

async function saveToList(type) {
    if(!activeUser) return alert("Log in first!");
    let dropEp = 0;
    if(type === 'dropped') {
        dropEp = prompt("Oof. What episode did you rage-quit on?");
        if(!dropEp) return;
    }
    await fetch('backend.php', {
        method: 'POST', body: JSON.stringify({ action: 'add_list', type: type, aid: currAnime.id, title: currAnime.title.english || currAnime.title.romaji, img: currAnime.coverImage.large, drop_ep: dropEp })
    });
    alert(type === 'top3' ? 'Pinned to Podium!' : `Saved to ${type}!`);
}

async function updateEp(aid, op) {
    let res = await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'update_ep', aid: aid, op: op }) });
    let d = await res.json();
    if(d.status === 'ok') {
        let el = document.getElementById('ep-cnt-' + aid);
        let val = parseInt(el.innerText) || 0;
        if(op === 'add') el.innerText = val + 1;
        if(op === 'sub' && val > 0) el.innerText = val - 1;
    }
}

async function changeAvatar(emoji) {
    await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'change_avatar', avatar: emoji }) });
    window.location.reload(); 
}

async function postReview() {
    if(!activeUser) return alert("Log in first!");
    let t = document.getElementById('tier_score').value;
    let txt = document.getElementById('rev_txt').value;
    let sp = document.getElementById('is_spoiler').checked;
    if(!t || !txt) return alert("Fill out the score and text!");

    let res = await fetch('backend.php', {
        method: 'POST', body: JSON.stringify({ action: 'add_rev', aid: currAnime.id, anime_title: currAnime.title.english || currAnime.title.romaji, tier_score: t, rev_txt: txt, is_spoiler: sp })
    });
    let d = await res.json();
    if(d.status === 'ok') {
        revDB[currAnime.id] = d.reviews; 
        renderReviews(); 
        document.getElementById('rev_txt').value = ''; 
    }
}

async function wTake(rid) {
    if(!activeUser) return alert("Log in to hype this up!");
    let res = await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'w_take', aid: currAnime.id, rid: rid }) });
    let d = await res.json();
    revDB[currAnime.id] = d.reviews;
    renderReviews(); 
}

function renderReviews() {
    let rHtml = '';
    let reviews = revDB[currAnime?.id] || [];
    let scoreCount = 0;
    
    const tierLabels = ["No votes", "💀 Why Does This Exist?", "🍿 Just a Timepass", "👍 One-Time Watch", "🔥 Really Good", "🐐 Peak Fiction"];
    const tierColors = ["#ffffff", "#ef4444", "#f97316", "#eab308", "#22c55e", "#a855f7"];
    let counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    
    if (reviews.length == 0) {
        document.getElementById('zenith-verdict-box').style.display = 'none';
        document.getElementById('rev-list').innerHTML = '<p style="color:grey;">No reviews yet. Be the first!</p>';
        return;
    } 

    document.getElementById('zenith-verdict-box').style.display = 'block';
    reviews.sort((a, b) => (b.wtakes?.length || 0) - (a.wtakes?.length || 0));

    reviews.forEach((r, index) => {
        if (r.score) { counts[r.score]++; scoreCount++; }
        let userVote = r.score ? `<span style="color:${tierColors[r.score]}; font-size:0.8rem;">[${tierLabels[r.score]}]</span>` : '';
        let reviewBody = r.spoiler ? `<div class="spoiler-text" onclick="this.classList.toggle('revealed')">⚠️ SPOILER: ${r.txt}</div>` : r.txt;
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
    
    if (scoreCount > 0) {
        let maxTier = 1; let maxVotes = 0;
        for (let i = 1; i <= 5; i++) { if (counts[i] > maxVotes) { maxVotes = counts[i]; maxTier = i; } }

        let maxPct = Math.round((maxVotes / scoreCount) * 100);
        document.getElementById('verdict-pct').innerText = maxPct + '%';
        document.getElementById('verdict-pct').style.color = tierColors[maxTier];
        document.getElementById('verdict-total').innerText = `${maxVotes}/${scoreCount} Votes`;
        document.getElementById('verdict-tier').innerText = tierLabels[maxTier];
        document.getElementById('verdict-tier').style.color = tierColors[maxTier];

        let offset = 125.6 - (125.6 * (maxPct / 100));
        let gaugeFill = document.getElementById('gauge-fill');
        gaugeFill.style.strokeDashoffset = offset;
        gaugeFill.style.stroke = tierColors[maxTier];

        let bdHtml = '';
        for (let i = 5; i >= 1; i--) {
            let pct = Math.round((counts[i] / scoreCount) * 100) || 0;
            bdHtml += `<div class="legend-row">
                          <span style="width: 150px; text-align: left;"><span style="color:${tierColors[i]};">●</span> ${tierLabels[i]}</span>
                          <div class="bar-bg"><div class="bar-fill" style="width:${pct}%; background:${tierColors[i]};"></div></div>
                          <span style="width: 40px; text-align: right;">${pct}%</span>
                       </div>`;
        }
        document.getElementById('verdict-breakdown').innerHTML = bdHtml;
    }
    document.getElementById('rev-list').innerHTML = rHtml;
}

async function openProfile(user) {
    viewedProfile = user;
    showModal('user-modal');
    document.getElementById('u-prof-loading').style.display = 'block';
    document.getElementById('u-prof-data').style.display = 'none';

    let res = await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'get_profile', target: user }) });
    let d = await res.json();
    
    document.getElementById('u-avatar').innerText = d.user.avatar || '👤';
    document.getElementById('u-name').innerText = user;
    document.getElementById('u-title').innerText = d.title;
    document.getElementById('u-followers').innerText = d.followers;
    
    let fb = document.getElementById('follow-btn');
    if(user === activeUser || !activeUser) fb.style.display = 'none';
    else { fb.style.display = 'inline-block'; fb.innerText = d.is_following ? 'Unfollow' : 'Follow'; }

    let mBox = document.getElementById('mutual-box');
    let mList = document.getElementById('mutual-list');
    mList.innerHTML = '';
    if(d.mutual.length > 0) {
        mBox.style.display = 'block';
        d.mutual.forEach(show => { mList.innerHTML += `<li style="cursor:pointer; color:var(--red);" onclick="loadAnime(${show.id})">${show.title}</li>`; });
    } else { mBox.style.display = 'none'; }

    let tBox = document.getElementById('top3-box');
    let tList = document.getElementById('top3-list');
    tList.innerHTML = '';
    if(d.top3 && d.top3.length > 0) {
        tBox.style.display = 'block';
        d.top3.forEach(show => { tList.innerHTML += `<li style="cursor:pointer; font-weight:bold;" onclick="loadAnime(${show.id})">🏅 ${show.title}</li>`; });
    } else { tBox.style.display = 'none'; }

    document.getElementById('u-prof-loading').style.display = 'none';
    document.getElementById('u-prof-data').style.display = 'block';
}

async function toggleFollow() {
    await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'toggle_follow', target: viewedProfile }) });
    openProfile(viewedProfile); 
}
function toggleInbox() { document.getElementById('inbox-drop').classList.toggle('show'); }
async function clearInbox() { await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'clear_inbox' }) }); window.location.reload(); }
async function voteVs(side) {
    let res = await fetch('backend.php', { method: 'POST', body: JSON.stringify({ action: 'vote_vs', side: side }) });
    let d = await res.json();
    let total = parseInt(d.v1) + parseInt(d.v2);
    document.getElementById('v1-pct').innerText = Math.round((d.v1/total)*100) + '%';
    document.getElementById('v2-pct').innerText = Math.round((d.v2/total)*100) + '%';
}
function switchTab(id) { document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); document.getElementById('tab-' + id).classList.add('active'); }
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function hideModal(id) { document.getElementById(id).style.display = 'none'; if(document.getElementById('trailer-container')) document.getElementById('trailer-container').innerHTML = ''; }
function toggleAuth(type) {
    if (type === 'register') { document.getElementById('f-login').style.display = 'none'; document.getElementById('f-register').style.display = 'block'; } 
    else { document.getElementById('f-register').style.display = 'none'; document.getElementById('f-login').style.display = 'block'; }
}
