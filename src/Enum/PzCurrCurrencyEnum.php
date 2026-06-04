<?php

declare(strict_types=1);

namespace Puzl\PzCurr\Enum;

/**
 * Catálogo de moedas ISO 4217 suportadas nativamente pelo pacote.
 *
 * O enum lista apenas os códigos; os metadados (numericCode, scale, symbol)
 * permanecem no {@see \Puzl\PzCurr\Currency\PzCurrCurrencyRegistry}, que é a
 * fonte única de verdade e também permite moedas customizadas (cripto, etc.)
 * que não fazem parte deste enum.
 */
enum PzCurrCurrencyEnum: string
{
    /** Emirados Árabes Unidos — dirham (AED). */
    case AED = 'AED';

    /** Argentina — peso (ARS). */
    case ARS = 'ARS';

    /** Austrália — dólar australiano (AUD). */
    case AUD = 'AUD';

    /** Bahrein — dinar (BHD). */
    case BHD = 'BHD';

    /** Brasil — real (BRL). */
    case BRL = 'BRL';

    /** Canadá — dólar canadense (CAD). */
    case CAD = 'CAD';

    /** Suíça e Liechtenstein — franco suíço (CHF). */
    case CHF = 'CHF';

    /** Chile — peso chileno (CLP). */
    case CLP = 'CLP';

    /** China — yuan renminbi (CNY). */
    case CNY = 'CNY';

    /** Colômbia — peso colombiano (COP). */
    case COP = 'COP';

    /** República Tcheca — coroa tcheca (CZK). */
    case CZK = 'CZK';

    /** Dinamarca — coroa dinamarquesa (DKK). */
    case DKK = 'DKK';

    /** Egito — libra egípcia (EGP). */
    case EGP = 'EGP';

    /** Zona do euro (União Europeia) — euro (EUR). */
    case EUR = 'EUR';

    /** Reino Unido — libra esterlina (GBP). */
    case GBP = 'GBP';

    /** Hong Kong — dólar de Hong Kong (HKD). */
    case HKD = 'HKD';

    /** Hungria — forint (HUF). */
    case HUF = 'HUF';

    /** Indonésia — rupia (IDR). */
    case IDR = 'IDR';

    /** Israel — novo shekel (ILS). */
    case ILS = 'ILS';

    /** Índia — rupia indiana (INR). */
    case INR = 'INR';

    /** Iraque — dinar iraquiano (IQD). */
    case IQD = 'IQD';

    /** Irã — rial (IRR). */
    case IRR = 'IRR';

    /** Islândia — coroa islandesa (ISK). */
    case ISK = 'ISK';

    /** Jordânia — dinar jordaniano (JOD). */
    case JOD = 'JOD';

    /** Japão — iene (JPY). */
    case JPY = 'JPY';

    /** Coreia do Sul — won (KRW). */
    case KRW = 'KRW';

    /** Kuwait — dinar kuwaitiano (KWD). */
    case KWD = 'KWD';

    /** México — peso mexicano (MXN). */
    case MXN = 'MXN';

    /** Malásia — ringgit (MYR). */
    case MYR = 'MYR';

    /** Noruega — coroa norueguesa (NOK). */
    case NOK = 'NOK';

    /** Nova Zelândia — dólar neozelandês (NZD). */
    case NZD = 'NZD';

    /** Omã — rial omanense (OMR). */
    case OMR = 'OMR';

    /** Peru — sol (PEN). */
    case PEN = 'PEN';

    /** Filipinas — peso filipino (PHP). */
    case PHP = 'PHP';

    /** Paquistão — rupia paquistanesa (PKR). */
    case PKR = 'PKR';

    /** Polônia — złoty (PLN). */
    case PLN = 'PLN';

    /** Catar — riyal (QAR). */
    case QAR = 'QAR';

    /** Romênia — leu (RON). */
    case RON = 'RON';

    /** Rússia — rublo (RUB). */
    case RUB = 'RUB';

    /** Arábia Saudita — riyal saudita (SAR). */
    case SAR = 'SAR';

    /** Suécia — coroa sueca (SEK). */
    case SEK = 'SEK';

    /** Singapura — dólar de Singapura (SGD). */
    case SGD = 'SGD';

    /** Tailândia — baht (THB). */
    case THB = 'THB';

    /** Turquia — lira turca (TRY). */
    case TRY = 'TRY';

    /** Taiwan — novo dólar de Taiwan (TWD). */
    case TWD = 'TWD';

    /** Ucrânia — hryvnia (UAH). */
    case UAH = 'UAH';

    /** Estados Unidos (e territórios associados) — dólar americano (USD). */
    case USD = 'USD';

    /** Uruguai — peso uruguaio (UYU). */
    case UYU = 'UYU';

    /** Vietnã — dong (VND). */
    case VND = 'VND';

    /** África do Sul — rand (ZAR). */
    case ZAR = 'ZAR';

    /**
     * Moeda padrão usada quando nenhuma é informada na criação do valor.
     */
    public static function default(): self
    {
        return self::BRL;
    }
}
