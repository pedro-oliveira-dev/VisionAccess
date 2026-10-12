document.addEventListener('DOMContentLoaded', function () {
    const lista = document.getElementById('lista-opcoes');
    const adicionar = document.getElementById('adicionar-opcao');

    if (!lista || !adicionar) {
        return;
    }

    function atualizarRemocao() {
        lista.querySelectorAll('.remover-opcao').forEach(function (botao) {
            botao.onclick = function () {
                const linhas = lista.querySelectorAll('.opcao-row');
                if (linhas.length <= 2) {
                    alert('Cada pergunta precisa ter pelo menos duas opções.');
                    return;
                }
                botao.closest('.opcao-row').remove();
            };
        });
    }

    adicionar.addEventListener('click', function () {
        const quantidade = lista.querySelectorAll('.opcao-row').length;
        if (quantidade >= 8) {
            alert('O limite desta tela é de 8 opções por pergunta.');
            return;
        }

        const novaOrdem = quantidade + 1;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-end mb-2 opcao-row';
        div.innerHTML = `
            <div class="col-md-6">
                <label class="form-label small">Texto</label>
                <input class="form-control" type="text" name="opcao_texto[]" maxlength="100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Pontuação</label>
                <input class="form-control" type="number" name="opcao_pontuacao[]" min="0" max="100" value="${novaOrdem - 1}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Ordem</label>
                <input class="form-control" type="number" name="opcao_ordem[]" min="1" max="999" value="${novaOrdem}" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-danger w-100 remover-opcao" type="button">Remover</button>
            </div>`;
        lista.appendChild(div);
        atualizarRemocao();
    });

    atualizarRemocao();
});
