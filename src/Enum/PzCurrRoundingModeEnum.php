<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Enum;

/**
 * Modos de arredondamento para operações monetárias.
 */
enum PzCurrRoundingModeEnum: string
{
    /**
     * Arredondamento comercial padrão: empates (.5) vão para longe de zero.
     * Use como default em preços, impostos e a maioria dos cálculos financeiros.
     */
    case HALF_UP = 'HALF_UP';

    /**
     * Empates (.5) vão em direção a zero.
     * Use quando quiser ser menos agressivo que HALF_UP exatamente no meio.
     */
    case HALF_DOWN = 'HALF_DOWN';

    /**
     * Empates (.5) vão para o dígito par mais próximo (banker's rounding).
     * Use em agregações estatísticas ou contábeis para reduzir viés acumulado.
     */
    case HALF_EVEN = 'HALF_EVEN';

    /**
     * Qualquer resto não zero arredonda para longe de zero.
     * Use quando o valor nunca pode ficar abaixo do necessário (ex.: taxas mínimas).
     */
    case UP = 'UP';

    /**
     * Qualquer resto não zero trunca em direção a zero.
     * Use quando o valor nunca pode exceder o permitido (ex.: descontos, teto de juros).
     */
    case DOWN = 'DOWN';

    /**
     * Arredonda em direção a +∞ (positivos sobem; negativos truncam).
     * Use quando precisa cobrir para cima no eixo numérico, independente do sinal.
     */
    case CEILING = 'CEILING';

    /**
     * Arredonda em direção a −∞ (negativos descem; positivos truncam).
     * Use quando precisa limitar para baixo no eixo numérico, independente do sinal.
     */
    case FLOOR = 'FLOOR';

    /**
     * Exige que o valor já esteja exato na escala; lança exceção se precisar arredondar.
     * Use em validações estritas ou contratos que não permitem perda de precisão.
     */
    case UNNECESSARY = 'UNNECESSARY';
}
