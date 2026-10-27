<?php

namespace Admin\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table = 'invoices';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['order_id', 'invoice_number', 'clinic_id', 'patient_id', 'appointment_id', 'currency', 'subtotal', 'tax', 'discount', 'total', 'courier_charges', 'discount_type', 'discount_value', 'pricing_updated_by', 'pricing_updated_at', 'status', 'payment_status', 'idempotency_key', 'created_by', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
}
