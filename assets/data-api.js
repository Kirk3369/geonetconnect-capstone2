window.GEONET = window.GEONET || {};
window.GEONET_LOAD = fetch("api/data.php", { credentials: "same-origin" })
    .then((res) => res.json())
    .then((data) => {
        if (data && data.ok && data.geonet) {
            window.GEONET = data.geonet;
        }
        return window.GEONET;
    })
    .catch(function () {
        return window.GEONET;
    });
