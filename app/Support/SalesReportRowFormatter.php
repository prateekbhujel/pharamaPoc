<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class SalesReportRowFormatter
{
    public static function headings(): array
    {
        return [
            'Sold At',
            'Tenant',
            'Hospital',
            'Pharmacy',
            'District',
            'Invoice',
            'Patient Code',
            'Patient Name',
            'Prescriber',
            'Category',
            'Medicine',
            'Generic',
            'Batch',
            'Quantity',
            'Unit Price',
            'Discount',
            'Tax',
            'Line Total',
            'Payment Status',
            'Supplier',
            'Cold Chain',
        ];
    }

    public static function toArray(object $row): array
    {
        return [
            CarbonImmutable::parse($row->sold_at)->format('Y-m-d H:i'),
            $row->tenant_name,
            $row->hospital_name,
            $row->pharmacy_name,
            $row->pharmacy_district,
            $row->invoice_number,
            $row->patient_code,
            $row->patient_name,
            $row->prescriber_name,
            $row->category_name,
            $row->brand_name,
            $row->generic_name,
            $row->batch_number,
            (int) $row->quantity,
            number_format((float) $row->unit_price, 2, '.', ''),
            number_format((float) $row->item_discount_amount, 2, '.', ''),
            number_format((float) $row->item_tax_amount, 2, '.', ''),
            number_format((float) $row->line_total, 2, '.', ''),
            $row->payment_status,
            $row->supplier_name,
            $row->is_cold_chain ? 'Yes' : 'No',
        ];
    }
}
