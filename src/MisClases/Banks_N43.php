<?php
namespace App\MisClases;


use App\MisClases\Banks_Helper;
use App\Entity\Banco;
use App\Entity\Tiposmovimiento;
/**
 * Importación y tratamiento de los extractos bancarios españoles que siguen la norma/cuaderno 43 de la 'Asociación Española de la Banca'.
 * Puede consultarse la especificación del formato en https://docs.bankinter.com/stf/plataformas/empresas/gestion/ficheros/formatos_fichero/norma_43_castellano.pdf.
 */
class Banks_N43
{
    /**
     * Cuentas leídas del fichero
     * @var Banks_N43_Account[]
     */
    public $accounts = [];

    /**
     * @var Banks_N43_Account
     */
    protected $_current_account;

    /**
     * @var Banks_N43_Entry
     */
    protected $_current_entry;

    protected $_record_count;


    /**
     * Representa un adeudo de tu cuenta
     */
    const TYPE_DEBIT = 'debit';

    /**
     * Representa un abono en tu cuenta
     */
    const TYPE_CREDIT = 'credit';

    const TYPE_UNKNOWN = 'unknown';

    public function parse($content)
    {
        $content = utf8_encode($content);

        $this->_record_count = 0;
        foreach (explode("\n", $content) as $line) {
            if (!$line) {
                continue;
            }

            $code = intval(substr($line, 0, 2));

            $method_name = "_parse_record_{$code}";
            if (method_exists($this, $method_name)) {
                $this->$method_name($line);
            } else {
                throw new Banks_N43_Exception("Invalid record type '{$code}' in line {$this->_record_count}");
            }

            $this->_record_count++;
        }
    }

    /**
     * Entrada 11 - Registro cabecera de cuenta (obligatorio)
     *
     * @param string $line
     *
     * @return Banks_N43_Account
     */
    protected function _parse_record_11($line)
    {
        $data = [
            'bank'            => substr($line, 2, 4),
            'office'          => substr($line, 6, 4),
            'account'         => substr($line, 10, 10),
            'number'          => substr($line, 2, 18),
            'date_start'      => self::_parse_date(substr($line, 20, 6)),
            'date_end'        => self::_parse_date(substr($line, 26, 6)),
            'type'            => substr($line, 32, 1) == 1 ? self::TYPE_DEBIT : (substr($line, 32, 1) == 2 ? self::TYPE_CREDIT : self::TYPE_UNKNOWN),
            'balance_initial' => floatval(substr($line, 33, 12) . '.' . substr($line, 45, 2)),
            'currency'        => Banks_Helper::currency_number2code(substr($line, 47, 3)),
            'mode'            => substr($line, 50, 1),// 1, 2 o 3
            'owner_name'      => trim(substr($line, 51, 26)),
            'entries'         => []
        ];

        if ($data['type'] == self::TYPE_DEBIT) {
            $data['balance_initial'] *= -1;
        }

        $account = new Banks_N43_Account();
        foreach ($data as $k => $v) {
            $account->$k = $v;
        }

        $this->_current_account = &$account;
        $this->accounts[] = &$account;

        return $account;
    }

    /**
     * Entrada 22 - Registro principal de movimiento (obligatorio)
     *
     * @param string $line
     *
     * @return Banks_N43_Entry
     */
    protected function _parse_record_22($line)
    {
        $banco  = new banco;
        $data = [
            'office'         => ltrim(substr($line, 6, 4), '0'),
            'date'           => "20".substr($line,10,2)."-".substr($line,12,2)."-".substr($line,14,2),
            'date_raw'       => substr($line, 10, 6),
            'date_value'     => self::_parse_date(substr($line, 16, 6)),
            'concept_common' => substr($line, 22, 2),
            'concept_own'    => substr($line, 24, 3),
            'type'           => substr($line, 27, 1) == 1 ? self::TYPE_DEBIT : (substr($line, 27, 1) == 2 ? self::TYPE_CREDIT : self::TYPE_UNKNOWN),
            'amount'         => floatval(substr($line, 28, 12) . '.' . substr($line, 40, 2)),
            'document'       => ltrim(substr($line, 42, 10), '0'),
            'refererence_1'  => ltrim(substr($line, 52, 12), '0'),
            'refererence_2'  => trim(substr($line, 64, 16)),
            'raw'            => $line,
            'banco'          => $banco,
            'concepts'       => []
        ];

        $entry = new Banks_N43_Entry();
        foreach ($data as $k => $v) {
            $entry->$k = $v;
        }

        $this->_current_entry =& $entry;
        $this->_current_account->entries[] =& $entry;


        
        $date_time = date_create_from_format('Y-m-d\TH:i:sP', $this->_current_entry->date."T15:52:01+00:00");
        $banco->setFechaBn($date_time);
        if ($this->_current_entry->type == "debit") {

            $banco->setImporteBn($this->_current_entry->amount * -1);
           
        }else{
            $banco->setImporteBn($this->_current_entry->amount);

        }

        return $entry;
    }


    /**
     * Entrada 23 - Registros complementarios de concepto (opcionales y hasta un máximo de 5)
     *
     * @param string $line
     *
     * @return Banks_N43_Entry
     */
    protected function _parse_record_23($line)
    {
        $this->_current_entry->concepts[substr($line, 2, 2)] = trim(substr($line, 4));
        $this->_current_entry->banco->setConceptoBn($this->_current_entry->concepts["01"]);
        $this->_current_entry->banco->setcategoriaBn($this->_concepto($this->_current_entry->concepts["01"]));
        return $this->_current_entry;
    }


    /**
     * Entrada 24 - Registro complementario de información de equivalencia del importe (opcional y sin valor contable)
     *
     * @param string $line
     *
     * @return Banks_N43_Entry
     */
    protected function _parse_record_24($line)
    {
        $this->_current_entry->currency_eq = Banks_Helper::currency_number2code(substr($line, 4, 3));
        $this->_current_entry->amount_eq = floatval(substr($line, 7, 12) . '.' . substr($line, 19, 2));

        return $this->_current_entry;
    }


    /**
     * Entrada 33 - Registro final de cuenta
     *
     * @param string $line
     *
     * @return Banks_N43_Account
     */
    protected function _parse_record_33($line)
    {
        $this->_current_account->balance_end = floatval(substr($line, 59, 12) . '.' . substr($line, 71, 2));


        /*Comprobaciones*/
        /* # Group level checks
        debit_count = 0
        debit = 0.0
        credit_count = 0
        credit = 0.0
        for st_line in st_group['lines']:
            if st_line['importe'] < 0:
                debit_count += 1
                debit -= st_line['importe']
            else:
                credit_count += 1
                credit += st_line['importe']
        if st_group['num_debe'] != debit_count:
            raise exceptions.Warning(
                _("Number of debit records doesn't match with the defined in "
                  "the last record of account."))
        if st_group['num_haber'] != credit_count:
            raise exceptions.Warning(
                _('Error in C43 file'),
                _("Number of credit records doesn't match with the defined "
                  "in the last record of account."))
        if abs(st_group['debe'] - debit) > 0.005:
            raise exceptions.Warning(
                _('Error in C43 file'),
                _("Debit amount doesn't match with the defined in the last "
                  "record of account."))
        if abs(st_group['haber'] - credit) > 0.005:
            raise exceptions.Warning(
                _("Credit amount doesn't match with the defined in the last "
                  "record of account."))
        # Note: Only perform this check if the balance is defined on the file
        # record, as some banks may leave it empty (zero) on some circumstances
        # (like CaixaNova extracts for VISA credit cards).
        if st_group['saldo_fin'] and st_group['saldo_ini']:
            balance = st_group['saldo_ini'] + credit - debit
            if abs(st_group['saldo_fin'] - balance) > 0.005:
                raise exceptions.Warning(
                    _("Final balance amount = (initial balance + credit "
                      "- debit) doesn't match with the defined in the last "
                      "record of account."))*/

        return $this->_current_account;
    }

    /**
     * Entrada 88 - Registro de fin de archivo
     *
     * @param string $line
     *
     * @throws Banks_N43_Exception
     */
    protected function _parse_record_88($line)
    {
        $record_count = substr($line, 20, 6);

        if ($record_count != $this->_record_count) {
            throw new Banks_N43_Exception("Number of records ({$this->_record_count}) doesn't match with the defined in the last record ({$record_count}).");
        }
    }

    private static function _parse_date($date)
    {
        return $date;
        
    }

    protected function _concepto($line)
    {
        $tipo = new tiposmovimiento;
        $concepto = explode(" ", $line);
        $gastofijo = array("RCBO.ASESORIAS", "COTIZACION", "RCBO.TGSS", "INTERNET", "TRF. PERIODICA 3528642972", "RECIBO BSSG","RCBO.TOTALENERGIES");
        $ventas= array("TRF.", "ORDENANTE", "FACTURACION COMERCIO");
        $proveedor=array("RCBO.DUPLACH","RCBO.ROYO","RCBO.DELBA","RCBO.SUMESA","RCBO.COMERCIAL", "RCBO.MAMPARAS", "RCBO.CAMACHO","RCBO.MEDITERRANEA","RCBO.ANTONIO","RCBO.SUMESA","RCBO.INDUSTRIA", "RCBO.ESPACIO", "RCBO.GME");
        $comision = array ("DESCUENTOS", "COMISIONES");
        $otros = array("5540XXXXXXXX5018");

        var_dump($concepto);
        die;

        foreach ($gastofijo as &$valor) {
            if (in_array($valor, $concepto)) {
                $tipo->setDescripcionTm("Gasto Fijo");
                return $tipo;
            }
        }

        foreach ($ventas as &$valor) {
            if (in_array($valor, $concepto)) {
                $tipo->setDescripcionTm("Ventas");
                return $tipo;
            }
        }

        foreach ($comision as &$valor) {
            if (in_array($valor, $concepto)) {
                $tipo->setDescripcionTm("Comisiones");
                return $tipo;
            }
        }


        foreach ($proveedor as &$valor) {
            if (in_array($valor, $concepto)) {
                $tipo->setDescripcionTm("Proveedor");
                return $tipo;
            }
        }

        foreach ($otros as &$valor) {
            if (in_array($valor, $concepto)) {
                $tipo->setDescripcionTm("Otros");
                return $tipo;
            }
        }

        $tipo->setDescripcionTm("Otros");
        return $tipo;


    }
}

class Banks_N43_Exception 
{

}

/**
 * @property int               $bank
 * @property int               $office
 * @property int               $account
 * @property int               $number
 * @property int               $date_start
 * @property int               $date_end
 * @property string            $type
 * @property float             $balance_initial
 * @property float             $balance_end
 * @property string            $currency
 * @property int               $mode
 * @property string            $owner_name
 * @property Banks_N43_Entry[] $entries
 */
class Banks_N43_Account
{
}


/**
 * @property int      $office
 * @property int      $date     Fecha de la operación, en formato marca temporal UNIX
 * @property string   $date_raw Fecha de la operación, en formato original
 * @property int      $date_value
 * @property string   $concept_common
 * @property string   $concept_own
 * @property string   $type     (debit or credit)
 * @property float    $amount
 * @property int      $document
 * @property string   $refererence_1
 * @property string   $refererence_2
 * @property string   $raw      Registro completo sin procesar
 * @property string[] $concepts
 * @property banco()  $banco
 */
class Banks_N43_Entry
{
}