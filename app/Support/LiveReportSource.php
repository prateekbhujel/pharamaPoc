<?php

namespace App\Support;

class LiveReportSource
{
    public static function sql(): string
    {
        return <<<'SQL'
            (
                SELECT base_rows.*, FALSE AS is_deleted
                FROM pharmacy_sale_export_rows AS base_rows
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM pharmacy_sale_export_overlays AS overlay
                    WHERE overlay.sale_item_id = base_rows.sale_item_id
                )
                UNION ALL
                SELECT overlay.*
                FROM pharmacy_sale_export_overlays AS overlay
                WHERE overlay.is_deleted = FALSE
            ) AS report_rows
        SQL;
    }
}
