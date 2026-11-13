
interface IndicadoresDashboard {
    total_produtos: number;
    quantidade_total_estoque: number;
    produtos_estoque_baixo: number;
    produtos_sem_estoque: number;
}

interface RespostaDashboard {
    sucesso: boolean;
    dados?: IndicadoresDashboard;
    mensagem?: string;
}


interface ResumoCategoria {
    nome_categoria: string;
    total_produtos: number;
    quantidade_total_estoque: number;
    produtos_estoque_baixo: number;
    produtos_zerados: number;
}

interface RespostaCategorias {
    sucesso: boolean;
    dados?: ResumoCategoria[];
    mensagem?: string;
}

declare const Chart: new (
    elemento: HTMLCanvasElement,
    configuracao: object
) => unknown;


async function buscarIndicadores(): Promise<IndicadoresDashboard> {
    const resposta = await fetch("../api/dashboard.php");

    if (!resposta.ok) {
        throw new Error("Erro ao consultar a API.");
    }

    const resultado = await resposta.json() as RespostaDashboard;

    if (!resultado.sucesso || !resultado.dados) {
        throw new Error(
            resultado.mensagem ?? "Não foi possível carregar os indicadores."
        );
    }

    return resultado.dados;
}


async function buscarCategorias(): Promise<ResumoCategoria[]> {
    const resposta = await fetch("../api/categorias.php");

    if (!resposta.ok) {
        throw new Error("Erro ao consultar as categorias.");
    }

    const resultado = await resposta.json() as RespostaCategorias;

    if (!resultado.sucesso || !resultado.dados) {
        throw new Error(
            resultado.mensagem ?? "Não foi possível carregar as categorias."
        );
    }

    return resultado.dados;
}


function analisarCategorias(categorias: ResumoCategoria[]): void {

    // 1. Somar o total de produtos e unidades
    const totais = categorias.reduce(
        (acumulador, categoria) => {
            acumulador.produtos += categoria.total_produtos;
            acumulador.unidades += categoria.quantidade_total_estoque;

            return acumulador;
        },
        {
            produtos: 0,
            unidades: 0
        }
    );

    // 2. Filtrar categorias com estoque crítico
    const categoriasCriticas = categorias.filter(
        categoria =>
            categoria.produtos_estoque_baixo > 0 ||
            categoria.produtos_zerados > 0
    );

    // 3. Transformar os dados para exibição
    const dadosExibicao = categorias.map(categoria => ({
        nome: categoria.nome_categoria,
        produtos: categoria.total_produtos,
        unidades: categoria.quantidade_total_estoque
    }));

    console.log("Totais das categorias:", totais);
    console.log("Categorias com estoque crítico:", categoriasCriticas);
    console.log("Dados para exibição:", dadosExibicao);
}

function criarGraficoCategorias(categorias: ResumoCategoria[]): void {
    const elemento = document.getElementById("grafico-categorias");

    if (!(elemento instanceof HTMLCanvasElement)) {
        console.error("Elemento do gráfico não encontrado.");
        return;
    }

    new Chart(elemento, {
        type: "bar",
        data: {
            labels: categorias.map(categoria => categoria.nome_categoria),
            datasets: [{
                label: "Unidades em estoque",
                data: categorias.map(
                    categoria => categoria.quantidade_total_estoque
                ),
                backgroundColor: [
                    "#0d6efd",
                    "#198754",
                    "#ffc107"
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

async function atualizarDashboard(): Promise<void> {
    try {
        const indicadores = await buscarIndicadores();

        const totalProdutos = document.getElementById(
            "indicador-total-produtos"
        );

        const estoqueBaixo = document.getElementById(
            "indicador-estoque-baixo"
        );

        const semEstoque = document.getElementById(
            "indicador-sem-estoque"
        );

        const totalUnidades = document.getElementById(
            "indicador-total-unidades"
        );

        if (
            !totalProdutos ||
            !estoqueBaixo ||
            !semEstoque ||
            !totalUnidades
        ) {
            throw new Error("Elementos do painel não encontrados.");
        }

        totalProdutos.textContent = String(indicadores.total_produtos);

        estoqueBaixo.textContent = String(
            indicadores.produtos_estoque_baixo
        );

        semEstoque.textContent = String(
            indicadores.produtos_sem_estoque
        );

        totalUnidades.textContent = String(
            indicadores.quantidade_total_estoque
        );

    } catch (erro: unknown) {
        console.error("Erro ao atualizar o painel:", erro);
    }
}


document.addEventListener("DOMContentLoaded", (): void => {
    void buscarCategorias()
    .then((categorias: ResumoCategoria[]): void => {
        analisarCategorias(categorias);
        criarGraficoCategorias(categorias);
    })
    .catch((erro: unknown): void => {
        console.error("Erro ao analisar categorias:", erro);
    });
});


