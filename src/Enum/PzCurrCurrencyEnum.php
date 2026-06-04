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
    case AED = 'AED';
    case ARS = 'ARS';
    case AUD = 'AUD';
    case BHD = 'BHD';
    case BRL = 'BRL';
    case CAD = 'CAD';
    case CHF = 'CHF';
    case CLP = 'CLP';
    case CNY = 'CNY';
    case COP = 'COP';
    case CZK = 'CZK';
    case DKK = 'DKK';
    case EGP = 'EGP';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case HKD = 'HKD';
    case HUF = 'HUF';
    case IDR = 'IDR';
    case ILS = 'ILS';
    case INR = 'INR';
    case IQD = 'IQD';
    case IRR = 'IRR';
    case ISK = 'ISK';
    case JOD = 'JOD';
    case JPY = 'JPY';
    case KRW = 'KRW';
    case KWD = 'KWD';
    case MXN = 'MXN';
    case MYR = 'MYR';
    case NOK = 'NOK';
    case NZD = 'NZD';
    case OMR = 'OMR';
    case PEN = 'PEN';
    case PHP = 'PHP';
    case PKR = 'PKR';
    case PLN = 'PLN';
    case QAR = 'QAR';
    case RON = 'RON';
    case RUB = 'RUB';
    case SAR = 'SAR';
    case SEK = 'SEK';
    case SGD = 'SGD';
    case THB = 'THB';
    case TRY = 'TRY';
    case TWD = 'TWD';
    case UAH = 'UAH';
    case USD = 'USD';
    case UYU = 'UYU';
    case VND = 'VND';
    case ZAR = 'ZAR';

    /**
     * Moeda padrão usada quando nenhuma é informada na criação do valor.
     */
    public static function default(): self
    {
        return self::BRL;
    }
}
