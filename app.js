fetch('data/news.json')
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {

    const container = document.getElementById('news');

    if (!container) {
      console.error("Container #news not found in HTML.");
      return;
    }

    if (!data.items || data.items.length === 0) {
      container.innerHTML = "<p>No posts available.</p>";
      return;
    }

    // Optional: sort newest first (if not already sorted)
    data.items.sort((a, b) =>
      new Date(b.pubDate) - new Date(a.pubDate)
    );

    data.items.forEach(item => {

const cell = document.createElement('div');
cell.className = "cell medium-4 small-12";

const formattedDate = item.pubDate
  ? new Date(item.pubDate).toLocaleDateString()
  : '';

const imageHTML = item.image
  ? `<img src="${item.image}" alt="${item.title}" loading="lazy">`
  : '';

cell.innerHTML = `
  <div class="card">

    ${imageHTML}

    <div class="card-section">
      <h3>
        <a href="${item.link}" target="_blank" rel="noopener">
          ${item.title}
        </a>
      </h3>

      <p>${item.description || ''}</p>

      <small>${item.source || ''} ${formattedDate ? '• ' + formattedDate : ''}</small>
    </div>

  </div>
`;

container.appendChild(cell);
    });

  })
  .catch(error => {
    console.error("Error loading news:", error);
    const container = document.getElementById('news');
    if (container) {
      container.innerHTML =
        "<p>Unable to load news at this time.</p>";
    }
  });
