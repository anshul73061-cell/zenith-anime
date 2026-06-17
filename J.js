async function getGrid(endpoint, elId, limit = 12) {
    try {
        let res = await fetch(`${API}${endpoint}&limit=${limit}`);
        
        // If the server returns a 504 or 500 error, throw an alert internally
        if (!res.ok) throw new Error('Jikan API is down or timing out.');
        
        let d = await res.json();
        
        // If there's no data array, stop here
        if (!d.data) throw new Error('No anime data received.');
        
        let html = '';
        d.data.forEach(a => {
            let t = a.title_english || a.title;
            html += `<div class="card" onclick="loadAnime(${a.mal_id})"><img src="${a.images.jpg.image_url}"><h4>${t}</h4></div>`;
        });
        document.getElementById(elId).innerHTML = html;
        
    } catch(e) { 
        console.log("Grid Error:", e); 
        // Show a friendly message on the website instead of an empty black void
        document.getElementById(elId).innerHTML = `<p style="color:var(--red); padding: 10px;">⚠️ The Anime Database (Jikan API) is currently down or overloaded. Please try refreshing in a few minutes.</p>`;
    }
}

