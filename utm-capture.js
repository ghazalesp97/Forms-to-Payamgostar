(function() {
  const params = new URLSearchParams(window.location.search);

  const utmMap = {
    utm_source: '_utmso',
    utm_medium: '_utmme',
    utm_term: '_utmte',
    utm_campaign: '_utmca',
    utm_content: '_utmco'
  };

  Object.entries(utmMap).forEach(([utmKey, cookieKey]) => {
    const value = params.get(utmKey);
    if (value) {
      const expires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString();
      document.cookie = `${cookieKey}=${encodeURIComponent(value)}; path=/; expires=${expires}`;
    }
  });
})();
