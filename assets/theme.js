(() => {
    document.documentElement.dataset.theme =
        localStorage.getItem('agribase.theme') === 'dark' ? 'dark' : 'light';
})();