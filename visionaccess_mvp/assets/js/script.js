// Lógica de Acessibilidade (RF09)
document.addEventListener('DOMContentLoaded', function() {
    const toggleContrast = document.getElementById('toggle-contrast');
    const increaseFont = document.getElementById('increase-font');
    const body = document.body;

    // Carregar preferências salvas
    if (localStorage.getItem('high-contrast') === 'true') {
        body.classList.add('high-contrast');
    }
    if (localStorage.getItem('large-font') === 'true') {
        body.classList.add('large-font');
    }

    // Alternar Alto Contraste
    toggleContrast.addEventListener('click', function() {
        body.classList.toggle('high-contrast');
        localStorage.setItem('high-contrast', body.classList.contains('high-contrast'));
    });

    // Alternar Tamanho da Fonte
    increaseFont.addEventListener('click', function() {
        body.classList.toggle('large-font');
        localStorage.setItem('large-font', body.classList.contains('large-font'));
    });
});