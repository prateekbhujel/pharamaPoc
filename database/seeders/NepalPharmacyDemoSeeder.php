<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class NepalPharmacyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->startOfMinute();

        $this->command?->info('Resetting reporting demo tables...');
        $this->truncateDemoTables();

        $locationClusters = $this->seedLocationClusters($now);
        $tenants = $this->seedTenants($now, $locationClusters);
        $hospitals = $this->seedHospitals($now, $tenants, $locationClusters);
        $this->seedUsers($now, $tenants, $hospitals);
        $pharmacies = $this->seedPharmacies($now, $hospitals, $locationClusters);
        $categories = $this->seedCategories($now);
        $suppliers = $this->seedSuppliers($now);
        $manufacturers = $this->seedManufacturers($now);
        $medicines = $this->seedMedicines($now, $categories, $suppliers, $manufacturers);
        $patients = $this->seedPatients($now, $locationClusters);
        $prescribers = $this->seedPrescribers($now, $hospitals);

        $this->seedSalesAndItems($now, $pharmacies, $patients, $prescribers, $medicines);
        $this->syncSequences([
            'location_clusters',
            'tenants',
            'hospitals',
            'pharmacies',
            'categories',
            'suppliers',
            'manufacturers',
            'medicines',
            'patients',
            'prescribers',
            'sales',
            'sale_items',
            'users',
        ]);

        $this->command?->info('Refreshing materialized export view...');
        DB::statement('REFRESH MATERIALIZED VIEW pharmacy_sale_export_rows');

        $this->command?->info('pharamaPOC demo data is ready.');
    }

    private function truncateDemoTables(): void
    {
        DB::statement('TRUNCATE TABLE sessions, report_exports, sale_items, sales, prescribers, patients, medicines, manufacturers, suppliers, categories, pharmacies, hospitals, tenants, location_clusters, users RESTART IDENTITY CASCADE');
    }

    private function seedLocationClusters($now): array
    {
        $domains = ['merocare.com.np', 'valleyhealth.com.np', 'swasthyahub.com.np', 'citymed.com.np'];

        $clusters = [
            ['Maharajgunj', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['New Baneshwor', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Thapathali', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Kalanki', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Koteshwor', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Boudha', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Chabahil', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Gongabu', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
            ['Kirtipur', 'Kathmandu', 'Kathmandu', 'Bagmati', '44618'],
            ['Pulchowk', 'Lalitpur', 'Lalitpur', 'Bagmati', '44700'],
            ['Patan Dhoka', 'Lalitpur', 'Lalitpur', 'Bagmati', '44700'],
            ['Jawalakhel', 'Lalitpur', 'Lalitpur', 'Bagmati', '44700'],
            ['Satdobato', 'Lalitpur', 'Lalitpur', 'Bagmati', '44700'],
            ['Bhaktapur Durbar', 'Bhaktapur', 'Bhaktapur', 'Bagmati', '44800'],
            ['Suryabinayak', 'Bhaktapur', 'Bhaktapur', 'Bagmati', '44800'],
            ['Madhyapur Thimi', 'Bhaktapur', 'Bhaktapur', 'Bagmati', '44800'],
            ['Budhanilkantha', 'Kathmandu', 'Kathmandu', 'Bagmati', '44622'],
            ['Naxal', 'Kathmandu', 'Kathmandu', 'Bagmati', '44600'],
        ];

        $rows = [];
        $index = 1;

        foreach ($clusters as [$area, $city, $district, $province, $postalCode]) {
            $rows[] = [
                'id' => $index,
                'area' => $area,
                'city' => $city,
                'district' => $district,
                'province' => $province,
                'postal_code' => $postalCode,
                'email_domain' => $domains[($index - 1) % count($domains)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $index++;
        }

        DB::table('location_clusters')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedTenants($now, array $locationClusters): array
    {
        $rows = [];
        $brands = ['Nobel', 'Grande', 'Bir', 'Norvic', 'Patan', 'Dhulikhel', 'Civil', 'Om', 'Kist', 'Alka', 'Medicity', 'Bheri', 'Lumbini', 'Janaki', 'Koshi', 'Rapti', 'Annapurna', 'Siddhartha', 'Everest', 'Seti'];
        $suffixes = ['Health Network', 'Medical Group', 'Care Alliance', 'Hospital System', 'Clinical Collective', 'Health Partners', 'Community Health Trust', 'Care Network', 'Medical Alliance', 'Regional Health Circle'];
        $organizationCount = max(1, (int) config('reporting.seed_organization_count', 200));

        for ($index = 0; $index < $organizationCount; $index++) {
            $location = $locationClusters[($index % count($locationClusters)) + 1];
            $name = $brands[$index % count($brands)].' '.$suffixes[intdiv($index, count($brands)) % count($suffixes)];
            $code = 'ORG-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $rows[] = [
                'id' => $index + 1,
                'code' => $code,
                'name' => $name,
                'billing_email' => 'billing@'.$location['email_domain'],
                'contact_phone' => '98010'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tenants')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedHospitals($now, array $tenants, array $locationClusters): array
    {
        $suffixes = ['General Hospital', 'Teaching Hospital', 'Women & Children Centre', 'Cardiac & Trauma Centre', 'Cancer Institute'];
        $rows = [];
        $id = 1;
        $hospitalMin = max(1, (int) config('reporting.seed_hospital_min', 2));
        $hospitalMax = max($hospitalMin, (int) config('reporting.seed_hospital_max', 4));
        $hospitalSpread = ($hospitalMax - $hospitalMin) + 1;

        foreach ($tenants as $tenantId => $tenant) {
            $hospitalCount = $hospitalMin + (($tenantId - 1) % $hospitalSpread);

            for ($offset = 0; $offset < $hospitalCount; $offset++) {
                $locationId = (($tenantId + $offset) % count($locationClusters)) + 1;
                $location = $locationClusters[$locationId];
                $suffix = $suffixes[$offset % count($suffixes)];
                $brand = explode(' ', $tenant['name'])[0];

                $rows[] = [
                    'id' => $id,
                    'tenant_id' => $tenantId,
                    'location_cluster_id' => $locationId,
                    'code' => 'HSP-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                    'name' => $brand.' '.$suffix,
                    'registration_number' => 'NMC-'.str_pad((string) (91000 + $id), 6, '0', STR_PAD_LEFT),
                    'contact_email' => 'ops@'.$location['email_domain'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $id++;
            }
        }

        DB::table('hospitals')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedPharmacies($now, array $hospitals, array $locationClusters): array
    {
        $pharmacySuffixes = ['Main Pharmacy', 'Emergency Pharmacy', 'Inpatient Pharmacy'];
        $rows = [];
        $id = 1;
        $pharmacyMin = max(1, (int) config('reporting.seed_pharmacy_min', 2));
        $pharmacyMax = max($pharmacyMin, (int) config('reporting.seed_pharmacy_max', 4));
        $pharmacySpread = ($pharmacyMax - $pharmacyMin) + 1;

        foreach ($hospitals as $hospitalId => $hospital) {
            $pharmacyCount = $pharmacyMin + (($hospitalId - 1) % $pharmacySpread);

            for ($offset = 0; $offset < $pharmacyCount; $offset++) {
                $locationId = (($hospitalId + $offset + 2) % count($locationClusters)) + 1;
                $location = $locationClusters[$locationId];
                $suffix = $pharmacySuffixes[$offset % count($pharmacySuffixes)];

                $rows[] = [
                    'id' => $id,
                    'hospital_id' => $hospitalId,
                    'location_cluster_id' => $locationId,
                    'code' => 'PHR-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                    'name' => explode(' ', $hospital['name'])[0].' '.$suffix,
                    'license_number' => 'DDA-'.str_pad((string) (140000 + $id), 6, '0', STR_PAD_LEFT),
                    'contact_email' => 'rx'.$id.'@'.$location['email_domain'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $id++;
            }
        }

        DB::table('pharmacies')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedUsers($now, array $tenants, array $hospitals): void
    {
        $rows = [
            [
                'id' => 1,
                'name' => 'Platform Admin',
                'username' => 'platform.admin',
                'email' => 'platform.admin@pharlab.test',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'platform_admin',
                'tenant_id' => null,
                'hospital_id' => null,
                'is_active' => true,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $firstHospitalByOrganization = collect($hospitals)
            ->groupBy('tenant_id')
            ->map(static fn ($organizationHospitals) => $organizationHospitals[0])
            ->all();

        $userId = 2;

        foreach ($tenants as $tenantId => $tenant) {
            $rows[] = [
                'id' => $userId++,
                'name' => $tenant['name'].' Admin',
                'username' => 'org'.str_pad((string) $tenantId, 3, '0', STR_PAD_LEFT).'.admin',
                'email' => 'org'.str_pad((string) $tenantId, 3, '0', STR_PAD_LEFT).'@pharlab.test',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'tenant_admin',
                'tenant_id' => $tenantId,
                'hospital_id' => null,
                'is_active' => true,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $hospital = $firstHospitalByOrganization[$tenantId] ?? null;

            if (! $hospital) {
                continue;
            }

            $rows[] = [
                'id' => $userId++,
                'name' => $hospital['name'].' Ops',
                'username' => 'hospital'.str_pad((string) $hospital['id'], 3, '0', STR_PAD_LEFT).'.admin',
                'email' => 'hospital'.str_pad((string) $hospital['id'], 3, '0', STR_PAD_LEFT).'@pharlab.test',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'hospital_admin',
                'tenant_id' => $tenantId,
                'hospital_id' => $hospital['id'],
                'is_active' => true,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInChunks('users', $rows, 250);
    }

    private function seedCategories($now): array
    {
        $names = [
            'Antibiotics',
            'Analgesics',
            'Cardiology',
            'Diabetes Care',
            'Respiratory',
            'Gastroenterology',
            'Vaccines',
            'Dermatology',
        ];

        $rows = collect($names)->values()->map(static function (string $name, int $index) use ($now): array {
            return [
                'id' => $index + 1,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('categories')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedSuppliers($now): array
    {
        $suppliers = [
            ['SUP-001', 'Nepa Med Distributors', 'Nepal', 1],
            ['SUP-002', 'Himal Trade Pharma', 'Nepal', 2],
            ['SUP-003', 'Bagmati Rx Logistics', 'Nepal', 2],
            ['SUP-004', 'Cipla Nepal Trade', 'India', 4],
            ['SUP-005', 'Sunrise Lifecare Supply', 'India', 3],
            ['SUP-006', 'Everest Vaccines Cold Chain', 'Nepal', 1],
            ['SUP-007', 'Metro Surgical & Pharma', 'Nepal', 2],
            ['SUP-008', 'Swasthya Import House', 'India', 5],
        ];

        $rows = collect($suppliers)->values()->map(static function (array $supplier, int $index) use ($now): array {
            return [
                'id' => $index + 1,
                'code' => $supplier[0],
                'name' => $supplier[1],
                'country' => $supplier[2],
                'lead_time_days' => $supplier[3],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('suppliers')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedManufacturers($now): array
    {
        $manufacturers = [
            ['Deurali-Janta Pharmaceuticals', 'Nepal'],
            ['Lomus Pharmaceuticals', 'Nepal'],
            ['Asian Pharmaceuticals', 'Nepal'],
            ['Nepal Pharmaceuticals Lab', 'Nepal'],
            ['Cipla', 'India'],
            ['Sun Pharma', 'India'],
            ['Torrent Pharma', 'India'],
            ['Intas', 'India'],
        ];

        $rows = collect($manufacturers)->values()->map(static function (array $manufacturer, int $index) use ($now): array {
            return [
                'id' => $index + 1,
                'name' => $manufacturer[0],
                'country' => $manufacturer[1],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('manufacturers')->insert($rows);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedMedicines($now, array $categories, array $suppliers, array $manufacturers): array
    {
        $genericPool = [
            'Paracetamol', 'Azithromycin', 'Cefixime', 'Amoxicillin', 'Pantoprazole', 'Metformin',
            'Amlodipine', 'Telmisartan', 'Rosuvastatin', 'Cetirizine', 'Montelukast', 'Salbutamol',
            'Insulin Glargine', 'Rabeprazole', 'Diclofenac', 'Ibuprofen', 'Atorvastatin', 'Clopidogrel',
            'Doxycycline', 'Cefpodoxime', 'Vitamin D3', 'Calcium Carbonate', 'Mupirocin', 'Hydrocortisone',
            'Rabies Vaccine', 'Influenza Vaccine', 'Omeprazole', 'Levofloxacin', 'Ondansetron', 'ORS',
        ];
        $forms = ['Tablet', 'Capsule', 'Suspension', 'Injection', 'Syrup', 'Inhaler', 'Cream', 'Vial'];
        $strengths = ['250 mg', '500 mg', '650 mg', '40 mg', '5 mg', '10 mg', '20 mg', '100 IU/ml'];
        $packSizes = ['10 tabs', '15 caps', '1 vial', '60 ml', '100 ml', '1 inhaler', '30 tabs', '5 ampoules'];

        $rows = [];
        $catalog = [];

        for ($id = 1; $id <= 280; $id++) {
            $generic = $genericPool[($id - 1) % count($genericPool)];
            $form = $forms[($id + 1) % count($forms)];
            $strength = $strengths[($id + 2) % count($strengths)];
            $categoryId = (($id - 1) % count($categories)) + 1;
            $supplierId = (($id + 1) % count($suppliers)) + 1;
            $manufacturerId = (($id + 3) % count($manufacturers)) + 1;
            $coldChain = in_array($generic, ['Rabies Vaccine', 'Influenza Vaccine', 'Insulin Glargine'], true);
            $unitPrice = round(45 + (($id % 17) * 12.75) + ($coldChain ? 55 : 0), 2);

            $rows[] = [
                'id' => $id,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'manufacturer_id' => $manufacturerId,
                'sku' => 'MED-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
                'brand_name' => 'KathRx '.$generic.' '.(($id % 4) + 1),
                'generic_name' => $generic,
                'dosage_form' => $form,
                'strength' => $strength,
                'pack_size' => $packSizes[($id + 4) % count($packSizes)],
                'unit_price' => $unitPrice,
                'is_cold_chain' => $coldChain,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $catalog[$id] = [
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'unit_price' => $unitPrice,
                'is_cold_chain' => $coldChain,
            ];
        }

        $this->insertInChunks('medicines', $rows, 500);

        return $catalog;
    }

    private function seedPatients($now, array $locationClusters): array
    {
        $firstNames = ['Aarav', 'Aayusha', 'Bibek', 'Sneha', 'Prakash', 'Sushma', 'Niraj', 'Asmita', 'Rajan', 'Samikshya'];
        $lastNames = ['Shrestha', 'Karki', 'Maharjan', 'Lama', 'Gurung', 'Adhikari', 'Rai', 'KC', 'Poudel', 'Tamang'];
        $insuranceProviders = [null, 'Shikhar Insurance', 'Neco Insurance', 'Sagarmatha Insurance', 'Himalayan General'];

        $rows = [];

        for ($id = 1; $id <= 9000; $id++) {
            $locationId = (($id * 5) % count($locationClusters)) + 1;
            $location = $locationClusters[$locationId];
            $firstName = $firstNames[$id % count($firstNames)];
            $lastName = $lastNames[($id + 2) % count($lastNames)];

            $rows[] = [
                'id' => $id,
                'location_cluster_id' => $locationId,
                'code' => 'PAT-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'full_name' => $firstName.' '.$lastName,
                'gender' => $id % 2 === 0 ? 'Female' : 'Male',
                'date_of_birth' => $now->copy()->subYears(18 + ($id % 55))->subDays($id % 365)->toDateString(),
                'insurance_provider' => $insuranceProviders[$id % count($insuranceProviders)],
                'contact_email' => strtolower($firstName).$id.'@'.$location['email_domain'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInChunks('patients', $rows, 1000);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedPrescribers($now, array $hospitals): array
    {
        $doctorFirstNames = ['Sanjay', 'Roshani', 'Pooja', 'Amit', 'Nisha', 'Prabesh', 'Rina', 'Anil', 'Kriti', 'Dipesh'];
        $doctorLastNames = ['Sharma', 'Bhandari', 'Joshi', 'Basnet', 'Regmi', 'Bhattarai', 'Malla', 'Rana', 'Dahal', 'Thapa'];
        $specialties = ['Internal Medicine', 'Cardiology', 'Pulmonology', 'Pediatrics', 'Orthopedics', 'General Practice', 'Oncology', 'Emergency Medicine'];

        $rows = [];
        $id = 1;

        foreach ($hospitals as $hospitalId => $hospital) {
            $doctorCount = 4 + ($hospitalId % 3);

            for ($slot = 0; $slot < $doctorCount; $slot++) {
                $firstName = $doctorFirstNames[($id + $slot) % count($doctorFirstNames)];
                $lastName = $doctorLastNames[($id + $slot + 1) % count($doctorLastNames)];

                $rows[] = [
                    'id' => $id,
                    'hospital_id' => $hospitalId,
                    'full_name' => 'Dr. '.$firstName.' '.$lastName,
                    'specialty' => $specialties[$id % count($specialties)],
                    'contact_email' => 'doctor'.$id.'@hospital.local',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $id++;
            }
        }

        $this->insertInChunks('prescribers', $rows, 500);

        return collect($rows)->keyBy('id')->all();
    }

    private function seedSalesAndItems($now, array $pharmacies, array $patients, array $prescribers, array $medicines): void
    {
        $salesTarget = max(1000, (int) config('reporting.demo_sales_target'));
        $salesBatch = [];
        $itemsBatch = [];
        $saleId = 1;
        $itemId = 1;
        $pharmacyCount = count($pharmacies);
        $patientCount = count($patients);
        $prescriberCount = count($prescribers);
        $medicineCount = count($medicines);

        $this->command?->info("Seeding {$salesTarget} pharmacy sales with repeated Kathmandu-style relationships...");

        while ($saleId <= $salesTarget) {
            $status = match (true) {
                $saleId % 19 === 0 => 'void',
                $saleId % 5 === 0 => 'insurance',
                $saleId % 3 === 0 => 'partial',
                default => 'paid',
            };

            $paymentMethod = match ($status) {
                'insurance' => 'insurance-claim',
                'partial' => $saleId % 2 === 0 ? 'card-plus-cash' : 'wallet-plus-cash',
                'void' => 'reversal',
                default => ['cash', 'card', 'wallet'][$saleId % 3],
            };

            $pharmacyId = (($saleId * 7) % $pharmacyCount) + 1;
            $patientId = $saleId % 7 === 0 ? null : (($saleId * 11) % $patientCount) + 1;
            $prescriberId = $saleId % 9 === 0 ? null : (($saleId * 13) % $prescriberCount) + 1;
            $soldAt = $now->copy()
                ->subDays($saleId % (15 * 365))
                ->setTime(6 + ($saleId % 13), ($saleId * 13) % 60);

            $grossAmount = 0.0;
            $discountAmount = 0.0;
            $taxAmount = 0.0;
            $netAmount = 0.0;
            $lineCount = 2 + ($saleId % 3);

            for ($lineNumber = 1; $lineNumber <= $lineCount; $lineNumber++) {
                $medicineId = (($saleId * ($lineNumber + 5)) % $medicineCount) + 1;
                $catalog = $medicines[$medicineId];
                $quantity = (($saleId + $lineNumber) % 5) + 1;
                $unitPrice = round($catalog['unit_price'] + (($saleId + $lineNumber) % 4) * 0.85, 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineDiscount = $saleId % 6 === 0 ? round($lineSubtotal * 0.05, 2) : 0.0;
                $lineTax = round(max(0, $lineSubtotal - $lineDiscount) * 0.13, 2);
                $lineTotal = round($lineSubtotal - $lineDiscount + $lineTax, 2);

                if ($status === 'void') {
                    $lineDiscount = $lineSubtotal;
                    $lineTax = 0.0;
                    $lineTotal = 0.0;
                }

                $grossAmount += $lineSubtotal;
                $discountAmount += $lineDiscount;
                $taxAmount += $lineTax;
                $netAmount += $lineTotal;

                $itemsBatch[] = [
                    'id' => $itemId,
                    'sale_id' => $saleId,
                    'medicine_id' => $medicineId,
                    'batch_number' => 'B-'.str_pad((string) (($saleId * 17 + $lineNumber) % 999999), 6, '0', STR_PAD_LEFT),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'expires_at' => $now->copy()->addMonths(6 + (($saleId + $lineNumber) % 24))->toDateString(),
                    'created_at' => $soldAt,
                    'updated_at' => $soldAt,
                ];
                $itemId++;
            }

            $salesBatch[] = [
                'id' => $saleId,
                'pharmacy_id' => $pharmacyId,
                'patient_id' => $patientId,
                'prescriber_id' => $prescriberId,
                'invoice_number' => 'INV-'.str_pad((string) $saleId, 8, '0', STR_PAD_LEFT),
                'payment_method' => $paymentMethod,
                'payment_status' => $status,
                'sold_at' => $soldAt,
                'gross_amount' => round($grossAmount, 2),
                'discount_amount' => round($discountAmount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_amount' => round($netAmount, 2),
                'created_at' => $soldAt,
                'updated_at' => $soldAt,
            ];

            if (count($salesBatch) >= 1000) {
                DB::table('sales')->insert($salesBatch);
                DB::table('sale_items')->insert($itemsBatch);
                $salesBatch = [];
                $itemsBatch = [];

                if ($saleId % 5000 === 0) {
                    $this->command?->info("Seeded {$saleId} sales...");
                }
            }

            $saleId++;
        }

        if ($salesBatch !== []) {
            DB::table('sales')->insert($salesBatch);
            DB::table('sale_items')->insert($itemsBatch);
        }
    }

    private function insertInChunks(string $table, array $rows, int $chunkSize): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function syncSequences(array $tables): void
    {
        foreach ($tables as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce((SELECT max(id) FROM {$table}), 1), true)");
        }
    }
}
