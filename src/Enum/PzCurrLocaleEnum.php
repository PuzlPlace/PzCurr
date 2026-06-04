<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Enum;

/**
 * Locales suportados para formatação monetária via NumberFormatter (ext-intl).
 *
 * Cada case é uma tag de locale ICU/BCP-47 consumida por
 * {@see \Puzl\PzCurr\Support\PzCurrFormatter} no modo Intl. Sem a extensão intl,
 * a formatação cai no modo manual e o locale é ignorado.
 *
 * Lista curada com os locales suportados pelo pacote; não cobre todo o catálogo ICU.
 */
enum PzCurrLocaleEnum: string
{
    /** Português (Brasil) — ex.: R$ 1.234,56. */
    case PT_BR = 'pt_BR';

    /** Português (Portugal) — ex.: 1 234,56 €. */
    case PT_PT = 'pt_PT';

    /** Inglês (Estados Unidos) — ex.: $1,234.56. */
    case EN_US = 'en_US';

    /** Inglês (Reino Unido) — ex.: £1,234.56. */
    case EN_GB = 'en_GB';

    /** Espanhol (Espanha) — ex.: 1234,56 €. */
    case ES_ES = 'es_ES';

    /** Espanhol (México) — ex.: $1,234.56. */
    case ES_MX = 'es_MX';

    /** Alemão (Alemanha) — ex.: 1.234,56 €. */
    case DE_DE = 'de_DE';

    /** Francês (França) — ex.: 1 234,56 €. */
    case FR_FR = 'fr_FR';

    /** Italiano (Itália) — ex.: 1.234,56 €. */
    case IT_IT = 'it_IT';

    /** Japonês (Japão) — sem casas decimais, ex.: ￥1.235. */
    case JA_JP = 'ja_JP';

    /** Chinês (China continental) — ex.: ￥1,234.56. */
    case ZH_CN = 'zh_CN';
}
