<script>
(function () {
    try {
        var key = 'tnr-theme';
        var versionKey = 'tnr-theme-preference-version';
        var currentVersion = 'light-default-v1';

        if (localStorage.getItem(versionKey) !== currentVersion) {
            localStorage.removeItem(key);
            localStorage.setItem(versionKey, currentVersion);
        }

        if (localStorage.getItem(key) === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    } catch (error) {}
})();
</script>
