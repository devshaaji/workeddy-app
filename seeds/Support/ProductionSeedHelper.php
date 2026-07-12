<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;

final class ProductionSeedHelper
{
    public const SHARED_PASSWORD = 'Password1!';

    private const ROLE_COUNTS = [
        'organization_admin' => 1,
        'safety_manager' => 5,
        'supervisor' => 6,
        'external_reviewer' => 5,
        'worker' => 18,
    ];

    private const FIRST_NAMES = [
        'Ada', 'Tunde', 'Mina', 'Chike', 'Ruth', 'Dayo', 'Ife', 'Bola', 'Seyi', 'Zainab',
        'Kelvin', 'Ngozi', 'Aisha', 'Emeka', 'Grace', 'Lanre', 'Bisola', 'Femi', 'Kemi', 'Uche',
        'Ivy', 'Kunle', 'Mary', 'Obi', 'Tara', 'Yemi', 'Victor', 'Halima', 'Samuel', 'Amaka',
        'David', 'Lara', 'Nora', 'Ibrahim', 'Damilola', 'Helen', 'Ola', 'Janet', 'Peter', 'Fatima',
    ];

    private const LAST_NAMES = [
        'Adeyemi', 'Okafor', 'Balogun', 'Ahmed', 'Nwosu', 'Cole', 'Ibrahim', 'Eze', 'Adebayo', 'Udo',
        'Mensah', 'Daniels', 'Akinola', 'Ogunleye', 'Bassey', 'Nnamani', 'Salami', 'Olowo', 'Yakubu', 'Afolabi',
        'Udoh', 'Akinyemi', 'Okechukwu', 'Bello', 'Onyema', 'Ojo', 'Chukwu', 'Adetola', 'Lawal', 'Okon',
    ];

    /** @var array<string, int> */
    private array $roleIds = [];
    private ?DateTimeImmutable $baseDate = null;

    public function __construct(private readonly Connection $db) {}

    public function seedPlatformAdmin(string $email = 'admin@workeddy.com', string $password = self::SHARED_PASSWORD): void
    {
        $now = $this->now();
        $roleId = $this->ensureSystemRole('super_admin', 'Super Admin', $now);

        $userId = $this->upsertUser([
            'uuid' => $this->uuid('platform', 'user', $email),
            'full_name' => 'WorkEddy Platform Admin',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role_id' => $roleId,
            'role_slug' => 'super_admin',
            'status' => 'active',
            'phone' => '+2347000000000',
            'employee_id' => 'WE-PLATFORM-001',
        ]);

        $this->upsertProfile($userId, [
            'uuid' => $this->uuid('platform', 'profile', $email),
            'full_name' => 'WorkEddy Platform Admin',
            'phone' => '+2347000000000',
            'job_title' => 'Platform Administrator',
        ]);
    }

    /**
     * @param array<string, mixed> $spec
     */
    public function seedOrganizationPack(array $spec): void
    {
        $this->db->transactional(function () use ($spec): void {
            $this->seedOrganizationPackInternal($spec);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function northernLogisticsSpec(): array
    {
        return [
            'key' => 'northern-logistics',
            'name' => 'Northern Apex Logistics',
            'slug' => 'northern-apex-logistics',
            'contact_email' => 'operations@northernapexlogistics.com',
            'phone' => '+2342015551101',
            'industry' => 'Warehousing and distribution',
            'subscription_start' => '2026-01-06 08:00:00',
            'base_datetime' => '2026-01-06 08:00:00',
            'worksites' => [
                [
                    'code' => 'lagos-distribution-hub',
                    'name' => 'Lagos Distribution Hub',
                    'location' => 'Amuwo-Odofin, Lagos',
                    'target_worker_count' => 68,
                    'actual_worker_count' => 61,
                    'notes' => 'Primary cross-docking and outbound fulfillment location serving southwest retail and e-commerce volumes.',
                    'departments' => [
                        [
                            'code' => 'operations',
                            'name' => 'Operations',
                            'job_roles' => [],
                            'tasks' => [],
                        ],
                        [
                            'code' => 'receiving',
                            'name' => 'Receiving',
                            'parent_code' => 'operations',
                            'job_roles' => ['forklift_operator' => 'Forklift Operator', 'receiving_associate' => 'Receiving Associate'],
                            'tasks' => [
                                ['code' => 'dock-unloading', 'name' => 'Dock unloading and pallet break-down', 'model' => 'reba', 'task_code' => 'NAL-RCV-101'],
                                ['code' => 'returns-triage', 'name' => 'Returns triage and restaging', 'model' => 'rula', 'task_code' => 'NAL-RCV-102'],
                            ],
                        ],
                        [
                            'code' => 'order-fulfillment',
                            'name' => 'Order Fulfillment',
                            'parent_code' => 'operations',
                            'job_roles' => ['picker_packer' => 'Picker Packer', 'inventory_controller' => 'Inventory Controller'],
                            'tasks' => [
                                ['code' => 'case-picking', 'name' => 'Case picking from lower rack locations', 'model' => 'reba', 'task_code' => 'NAL-FUL-201'],
                                ['code' => 'carton-packing', 'name' => 'Carton packing and label application', 'model' => 'rula', 'task_code' => 'NAL-FUL-202'],
                            ],
                        ],
                        [
                            'code' => 'outbound-loading',
                            'name' => 'Outbound Loading',
                            'parent_code' => 'operations',
                            'job_roles' => ['loader' => 'Loader'],
                            'tasks' => [
                                ['code' => 'truck-loading', 'name' => 'Truck loading and load securement', 'model' => 'reba', 'task_code' => 'NAL-OUT-301'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'ibadan-sort-center',
                    'name' => 'Ibadan Sort Center',
                    'location' => 'Oluyole Industrial Estate, Ibadan',
                    'target_worker_count' => 46,
                    'actual_worker_count' => 41,
                    'notes' => 'Regional sortation and reverse-logistics node focused on parcel processing and line-haul staging.',
                    'departments' => [
                        [
                            'code' => 'site-services',
                            'name' => 'Site Services',
                            'job_roles' => [],
                            'tasks' => [],
                        ],
                        [
                            'code' => 'sortation',
                            'name' => 'Sortation',
                            'parent_code' => 'site-services',
                            'job_roles' => ['sortation_operator' => 'Sortation Operator', 'linehaul_coordinator' => 'Linehaul Coordinator'],
                            'tasks' => [
                                ['code' => 'parcel-sort', 'name' => 'Parcel sort lane clearing and induction', 'model' => 'rula', 'task_code' => 'NAL-SRT-401'],
                                ['code' => 'bag-build', 'name' => 'Bag build-up and cage staging', 'model' => 'reba', 'task_code' => 'NAL-SRT-402'],
                            ],
                        ],
                        [
                            'code' => 'quality-control',
                            'name' => 'Quality Control',
                            'parent_code' => 'site-services',
                            'job_roles' => ['quality_technician' => 'Quality Technician'],
                            'tasks' => [
                                ['code' => 'damage-inspection', 'name' => 'Damage inspection and photo capture', 'model' => 'rula', 'task_code' => 'NAL-QC-501'],
                            ],
                        ],
                        [
                            'code' => 'fleet-support',
                            'name' => 'Fleet Support',
                            'parent_code' => 'site-services',
                            'job_roles' => ['yard_operator' => 'Yard Operator'],
                            'tasks' => [
                                ['code' => 'trailer-coupling', 'name' => 'Trailer coupling checks and line setup', 'model' => 'reba', 'task_code' => 'NAL-FLT-601'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function riverbendManufacturingSpec(): array
    {
        return [
            'key' => 'riverbend-manufacturing',
            'name' => 'Riverbend Components Manufacturing',
            'slug' => 'riverbend-components-manufacturing',
            'contact_email' => 'safety@riverbendcomponents.com',
            'phone' => '+2342015552202',
            'industry' => 'Light manufacturing and assembly',
            'subscription_start' => '2026-02-03 07:30:00',
            'base_datetime' => '2026-02-03 07:30:00',
            'worksites' => [
                [
                    'code' => 'main-assembly-campus',
                    'name' => 'Main Assembly Campus',
                    'location' => 'Ikeja Industrial Axis, Lagos',
                    'target_worker_count' => 82,
                    'actual_worker_count' => 76,
                    'notes' => 'Multi-line component assembly site with repetitive bench work, kitting, and packaging operations.',
                    'departments' => [
                        ['code' => 'plant-operations', 'name' => 'Plant Operations', 'job_roles' => [], 'tasks' => []],
                        [
                            'code' => 'assembly-line-a',
                            'name' => 'Assembly Line A',
                            'parent_code' => 'plant-operations',
                            'job_roles' => ['assembly_operator' => 'Assembly Operator', 'line_lead' => 'Line Lead'],
                            'tasks' => [
                                ['code' => 'fastener-install', 'name' => 'Fastener install and torque verification', 'model' => 'rula', 'task_code' => 'RCM-ASM-101'],
                                ['code' => 'component-kitting', 'name' => 'Component kitting for line replenishment', 'model' => 'reba', 'task_code' => 'RCM-ASM-102'],
                            ],
                        ],
                        [
                            'code' => 'packaging',
                            'name' => 'Packaging',
                            'parent_code' => 'plant-operations',
                            'job_roles' => ['packaging_operator' => 'Packaging Operator'],
                            'tasks' => [
                                ['code' => 'carton-sealing', 'name' => 'Carton sealing and pallet layer build', 'model' => 'reba', 'task_code' => 'RCM-PKG-201'],
                                ['code' => 'label-apply', 'name' => 'Label apply and scan verification', 'model' => 'rula', 'task_code' => 'RCM-PKG-202'],
                            ],
                        ],
                        [
                            'code' => 'maintenance',
                            'name' => 'Maintenance',
                            'parent_code' => 'plant-operations',
                            'job_roles' => ['maintenance_technician' => 'Maintenance Technician'],
                            'tasks' => [
                                ['code' => 'guard-changeout', 'name' => 'Machine guard changeout and alignment', 'model' => 'reba', 'task_code' => 'RCM-MNT-301'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'finishing-and-qc',
                    'name' => 'Finishing and QC Center',
                    'location' => 'Ogun-Guangdong Free Trade Zone',
                    'target_worker_count' => 38,
                    'actual_worker_count' => 34,
                    'notes' => 'Finishing, inspection, and dispatch preparation site for completed assemblies.',
                    'departments' => [
                        ['code' => 'support-services', 'name' => 'Support Services', 'job_roles' => [], 'tasks' => []],
                        [
                            'code' => 'quality-lab',
                            'name' => 'Quality Lab',
                            'parent_code' => 'support-services',
                            'job_roles' => ['quality_analyst' => 'Quality Analyst'],
                            'tasks' => [
                                ['code' => 'bench-inspection', 'name' => 'Bench inspection and defect marking', 'model' => 'rula', 'task_code' => 'RCM-QA-401'],
                                ['code' => 'sample-weighing', 'name' => 'Sample weighing and dimensional checks', 'model' => 'rula', 'task_code' => 'RCM-QA-402'],
                            ],
                        ],
                        [
                            'code' => 'dispatch-prep',
                            'name' => 'Dispatch Preparation',
                            'parent_code' => 'support-services',
                            'job_roles' => ['material_handler' => 'Material Handler', 'dispatch_clerk' => 'Dispatch Clerk'],
                            'tasks' => [
                                ['code' => 'pallet-wrap', 'name' => 'Pallet wrapping and manifest staging', 'model' => 'reba', 'task_code' => 'RCM-DSP-501'],
                                ['code' => 'export-doc-pack', 'name' => 'Export document pack-out and pouching', 'model' => 'rula', 'task_code' => 'RCM-DSP-502'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sunriseHealthSpec(): array
    {
        return [
            'key' => 'sunrise-health-network',
            'name' => 'Sunrise Health Network',
            'slug' => 'sunrise-health-network',
            'contact_email' => 'prevention@sunrisehealthnetwork.com',
            'phone' => '+2342015553303',
            'industry' => 'Healthcare operations',
            'subscription_start' => '2026-03-10 06:45:00',
            'base_datetime' => '2026-03-10 06:45:00',
            'worksites' => [
                [
                    'code' => 'central-hospital',
                    'name' => 'Central Hospital Campus',
                    'location' => 'Garki District, Abuja',
                    'target_worker_count' => 74,
                    'actual_worker_count' => 69,
                    'notes' => 'Acute care campus with inpatient wards, environmental services, and patient support operations.',
                    'departments' => [
                        ['code' => 'clinical-operations', 'name' => 'Clinical Operations', 'job_roles' => [], 'tasks' => []],
                        [
                            'code' => 'patient-care',
                            'name' => 'Patient Care Support',
                            'parent_code' => 'clinical-operations',
                            'job_roles' => ['patient_care_assistant' => 'Patient Care Assistant', 'ward_supervisor' => 'Ward Supervisor'],
                            'tasks' => [
                                ['code' => 'patient-transfer', 'name' => 'Bed-to-chair patient transfer support', 'model' => 'reba', 'task_code' => 'SHN-PTC-101'],
                                ['code' => 'medication-cart', 'name' => 'Medication cart setup and restocking', 'model' => 'rula', 'task_code' => 'SHN-PTC-102'],
                            ],
                        ],
                        [
                            'code' => 'environmental-services',
                            'name' => 'Environmental Services',
                            'parent_code' => 'clinical-operations',
                            'job_roles' => ['environmental_services_technician' => 'Environmental Services Technician'],
                            'tasks' => [
                                ['code' => 'bed-turnover', 'name' => 'Room bed turnover and wipe-down', 'model' => 'reba', 'task_code' => 'SHN-ENV-201'],
                                ['code' => 'linen-haul', 'name' => 'Soiled linen haul and bag exchange', 'model' => 'reba', 'task_code' => 'SHN-ENV-202'],
                            ],
                        ],
                        [
                            'code' => 'sterile-processing',
                            'name' => 'Sterile Processing',
                            'parent_code' => 'clinical-operations',
                            'job_roles' => ['sterile_processing_technician' => 'Sterile Processing Technician'],
                            'tasks' => [
                                ['code' => 'instrument-tray-pack', 'name' => 'Instrument tray assembly and pack wrap', 'model' => 'rula', 'task_code' => 'SHN-STR-301'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'community-care-center',
                    'name' => 'Community Care and Laundry Center',
                    'location' => 'Wuse II, Abuja',
                    'target_worker_count' => 36,
                    'actual_worker_count' => 32,
                    'notes' => 'Outpatient support and linen processing center serving the hospital network.',
                    'departments' => [
                        ['code' => 'shared-services', 'name' => 'Shared Services', 'job_roles' => [], 'tasks' => []],
                        [
                            'code' => 'ambulatory-support',
                            'name' => 'Ambulatory Support',
                            'parent_code' => 'shared-services',
                            'job_roles' => ['outpatient_support_specialist' => 'Outpatient Support Specialist'],
                            'tasks' => [
                                ['code' => 'wheelchair-assist', 'name' => 'Wheelchair escort and handoff support', 'model' => 'reba', 'task_code' => 'SHN-AMB-401'],
                                ['code' => 'supply-restock', 'name' => 'Clinic supply restock and shelf reach', 'model' => 'rula', 'task_code' => 'SHN-AMB-402'],
                            ],
                        ],
                        [
                            'code' => 'linen-processing',
                            'name' => 'Linen Processing',
                            'parent_code' => 'shared-services',
                            'job_roles' => ['laundry_operator' => 'Laundry Operator', 'linen_coordinator' => 'Linen Coordinator'],
                            'tasks' => [
                                ['code' => 'linen-sorting', 'name' => 'Linen sorting and washer loading', 'model' => 'reba', 'task_code' => 'SHN-LIN-501'],
                                ['code' => 'fold-pack', 'name' => 'Fold, pack, and cart build for ward issue', 'model' => 'rula', 'task_code' => 'SHN-LIN-502'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function seedOrganizationPackInternal(array $spec): void
    {
        $this->baseDate = new DateTimeImmutable((string) ($spec['base_datetime'] ?? $spec['subscription_start'] ?? '2026-01-05 08:00:00'));
        $this->roleIds = $this->loadRoleIds();
        $organization = $this->upsertOrganizationRecord($spec);
        $this->upsertSubscriptionRecord($organization, $spec);

        $structure = $this->seedStructure($organization, $spec);
        $users = $this->seedUsers($organization, $spec, $structure);
        $workflow = $this->seedTasksAssessmentsAndActions($organization, $spec, $structure, $users);
        $this->seedFeedback($organization, $spec, $workflow, $users);
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{id:int,uuid:string}
     */
    private function upsertOrganizationRecord(array $spec): array
    {
        $uuid = $this->uuid((string) $spec['key'], 'organization');
        $existing = $this->db->fetchAssociative('SELECT id FROM organizations WHERE uuid = ? OR slug = ?', [$uuid, $spec['slug']]);
        $data = [
            'uuid' => $uuid,
            'name' => $spec['name'],
            'slug' => $spec['slug'],
            'status' => 'active',
            'contact_email' => $spec['contact_email'],
            'phone' => $spec['phone'],
            'updated_at' => $this->now(),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('organizations', $data + ['created_at' => $this->now()]);
            return ['id' => (int) $this->db->lastInsertId(), 'uuid' => $uuid];
        }

        $this->db->update('organizations', $data, ['id' => (int) $existing['id']]);

        return ['id' => (int) $existing['id'], 'uuid' => $uuid];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $spec
     */
    private function upsertSubscriptionRecord(array $organization, array $spec): void
    {
        $uuid = $this->uuid((string) $spec['key'], 'subscription');
        $existing = $this->db->fetchAssociative('SELECT id FROM subscriptions WHERE uuid = ? OR organization_id = ?', [$uuid, $organization['id']]);
        $start = (string) $spec['subscription_start'];
        $periodEnd = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d H:i:s');
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'plan_code' => 'professional',
            'plan_name' => 'Professional',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'start_date' => $start,
            'expiry_date' => null,
            'activated_at' => $start,
            'suspended_at' => null,
            'suspended_reason' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'auto_renew' => 1,
            'current_period_start' => $start,
            'current_period_end' => $periodEnd,
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->db->insert('subscriptions', $data + ['created_at' => $this->now()]);
        } else {
            $this->db->update('subscriptions', $data, ['id' => (int) $existing['id']]);
        }

        $usage = [
            'active_worksites' => count((array) $spec['worksites']),
            'active_members' => array_sum(self::ROLE_COUNTS),
            'active_tasks' => $this->countTasks($spec),
            'completed_assessments' => $this->countTasks($spec) * 2,
            'open_corrective_actions' => $this->countTasks($spec),
        ];

        $usageExisting = $this->db->fetchAssociative(
            'SELECT id FROM subscription_usage WHERE subscription_uuid = ? AND period_start = ?',
            [$uuid, (new DateTimeImmutable($start))->format('Y-m-01')],
        );

        $usageData = [
            'subscription_uuid' => $uuid,
            'period_start' => (new DateTimeImmutable($start))->format('Y-m-01'),
            'period_end' => (new DateTimeImmutable($start))->format('Y-m-t'),
            'usage_data' => json_encode($usage, JSON_THROW_ON_ERROR),
            'updated_at' => $this->now(),
        ];

        if ($usageExisting === false) {
            $this->db->insert('subscription_usage', $usageData);
        } else {
            $this->db->update('subscription_usage', $usageData, ['id' => (int) $usageExisting['id']]);
        }
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $spec
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function seedStructure(array $organization, array $spec): array
    {
        $worksites = [];
        $departments = [];
        $jobRoles = [];

        foreach ((array) $spec['worksites'] as $worksiteIndex => $worksiteSpec) {
            $worksite = $this->upsertWorksite($organization, (string) $spec['key'], $worksiteSpec, $worksiteIndex);
            $worksites[(string) $worksiteSpec['code']] = $worksite;
            $this->upsertPilotSite($organization, $worksite, $spec, $worksiteSpec, $worksiteIndex);

            foreach ((array) $worksiteSpec['departments'] as $departmentIndex => $departmentSpec) {
                $parentId = null;
                if (isset($departmentSpec['parent_code']) && isset($departments[(string) $worksiteSpec['code'] . ':' . (string) $departmentSpec['parent_code']])) {
                    $parentId = $departments[(string) $worksiteSpec['code'] . ':' . (string) $departmentSpec['parent_code']]['id'];
                }

                $department = $this->upsertDepartment(
                    $organization,
                    $worksite,
                    (string) $spec['key'],
                    $departmentSpec,
                    $parentId,
                    $departmentIndex,
                );
                $deptKey = (string) $worksiteSpec['code'] . ':' . (string) $departmentSpec['code'];
                $departments[$deptKey] = $department;

                foreach ((array) $departmentSpec['job_roles'] as $roleCode => $roleName) {
                    $jobRoles[$deptKey . ':' . $roleCode] = $this->upsertJobRole(
                        $organization,
                        $department,
                        (string) $spec['key'],
                        (string) $roleCode,
                        (string) $roleName,
                    );
                }
            }
        }

        return [
            'worksites' => $worksites,
            'departments' => $departments,
            'job_roles' => $jobRoles,
        ];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $spec
     * @param array<string, array<string, array<string, mixed>>> $structure
     * @return array<string, list<array<string, mixed>>>
     */
    private function seedUsers(array $organization, array $spec, array $structure): array
    {
        $usersByRole = [];
        $worksites = array_values($structure['worksites']);
        $departments = array_values(array_filter(
            $structure['departments'],
            static fn(array $department): bool => $department['parent_department_id'] !== null
        ));
        $jobRoles = array_values($structure['job_roles']);
        $domains = $this->organizationEmailDomain((string) $spec['slug']);

        foreach (self::ROLE_COUNTS as $roleSlug => $count) {
            $usersByRole[$roleSlug] = [];
            for ($index = 1; $index <= $count; $index++) {
                [$fullName, $phone, $employeeId] = $this->personDetails((string) $spec['key'], $roleSlug, $index);
                $email = $this->personEmail($fullName, $domains, $roleSlug, $index);
                $userId = $this->upsertUser([
                    'uuid' => $this->uuid((string) $spec['key'], 'user', $roleSlug, (string) $index),
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => password_hash(self::SHARED_PASSWORD, PASSWORD_BCRYPT),
                    'role_id' => $this->roleId($roleSlug),
                    'role_slug' => $roleSlug,
                    'status' => 'active',
                    'phone' => $phone,
                    'employee_id' => $employeeId,
                ]);

                $this->upsertProfile($userId, [
                    'uuid' => $this->uuid((string) $spec['key'], 'profile', $roleSlug, (string) $index),
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'job_title' => $this->jobTitleForRole($roleSlug, $index),
                ]);

                $scope = $this->membershipScope($roleSlug, $index, $worksites, $departments, $jobRoles);
                $membershipId = $this->upsertMembership($userId, $organization, [
                    'uuid' => $this->uuid((string) $spec['key'], 'membership', $roleSlug, (string) $index),
                    'role_id' => $this->roleId($roleSlug),
                    'role_slug' => $roleSlug,
                    'worksite_id' => $scope['worksite']['id'] ?? null,
                    'department_id' => $scope['department']['id'] ?? null,
                    'job_role_id' => $scope['job_role']['id'] ?? null,
                ]);

                $usersByRole[$roleSlug][] = [
                    'id' => $userId,
                    'membership_id' => $membershipId,
                    'uuid' => $this->uuid((string) $spec['key'], 'user', $roleSlug, (string) $index),
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'employee_id' => $employeeId,
                    'role_slug' => $roleSlug,
                    'worksite' => $scope['worksite'] ?? null,
                    'department' => $scope['department'] ?? null,
                    'job_role' => $scope['job_role'] ?? null,
                ];
            }
        }

        return $usersByRole;
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $spec
     * @param array<string, array<string, array<string, mixed>>> $structure
     * @param array<string, list<array<string, mixed>>> $users
     * @return array{tasks:list<array<string,mixed>>,baselines:list<array<string,mixed>>,followups:list<array<string,mixed>>,actions:list<array<string,mixed>>}
     */
    private function seedTasksAssessmentsAndActions(array $organization, array $spec, array $structure, array $users): array
    {
        $tasks = [];
        $baselines = [];
        $followUps = [];
        $actions = [];
        $departmentTaskCounter = 0;

        foreach ((array) $spec['worksites'] as $worksiteSpec) {
            $worksite = $structure['worksites'][(string) $worksiteSpec['code']];
            foreach ((array) $worksiteSpec['departments'] as $departmentSpec) {
                $deptKey = (string) $worksiteSpec['code'] . ':' . (string) $departmentSpec['code'];
                $department = $structure['departments'][$deptKey];
                foreach ((array) $departmentSpec['tasks'] as $taskSpec) {
                    $taskRole = $this->firstTaskJobRole($structure['job_roles'], $deptKey);
                    $task = $this->upsertTask($organization, $worksite, $department, $taskRole, (string) $spec['key'], $taskSpec);
                    $tasks[] = $task;

                    $baseline = $this->upsertAssessment(
                        $organization,
                        $task,
                        $users['worker'][$departmentTaskCounter % count($users['worker'])]['id'],
                        $users['external_reviewer'][$departmentTaskCounter % count($users['external_reviewer'])],
                        (string) $spec['key'],
                        (string) $taskSpec['code'] . '-baseline',
                        'locked',
                        true,
                        $this->baselineAssessmentData($task, $departmentTaskCounter),
                        0,
                    );
                    $followUp = $this->upsertAssessment(
                        $organization,
                        $task,
                        $users['worker'][($departmentTaskCounter + 3) % count($users['worker'])]['id'],
                        $users['external_reviewer'][($departmentTaskCounter + 1) % count($users['external_reviewer'])],
                        (string) $spec['key'],
                        (string) $taskSpec['code'] . '-followup',
                        $departmentTaskCounter % 3 === 0 ? 'locked' : 'reviewed',
                        false,
                        $this->followUpAssessmentData($task, $departmentTaskCounter),
                        42,
                    );
                    $baselines[] = $baseline;
                    $followUps[] = $followUp;

                    $extraStatus = $departmentTaskCounter % 2 === 0 ? 'pending_review' : 'draft';
                    $this->upsertAssessment(
                        $organization,
                        $task,
                        $users['worker'][($departmentTaskCounter + 6) % count($users['worker'])]['id'],
                        $users['safety_manager'][$departmentTaskCounter % count($users['safety_manager'])],
                        (string) $spec['key'],
                        (string) $taskSpec['code'] . '-current',
                        $extraStatus,
                        false,
                        $this->currentAssessmentData($task, $departmentTaskCounter),
                        96,
                    );

                    $recommendation = $this->upsertRecommendation(
                        $organization,
                        $baseline,
                        $users['safety_manager'][$departmentTaskCounter % count($users['safety_manager'])],
                        (string) $spec['key'],
                        (string) $taskSpec['code'],
                        $this->recommendationData($task, $departmentTaskCounter),
                    );

                    $action = $this->upsertCorrectiveAction(
                        $organization,
                        $baseline,
                        $recommendation,
                        $users['supervisor'][$departmentTaskCounter % count($users['supervisor'])],
                        $users['safety_manager'][($departmentTaskCounter + 2) % count($users['safety_manager'])],
                        (string) $spec['key'],
                        (string) $taskSpec['code'],
                        $this->actionData($departmentTaskCounter),
                    );
                    $actions[] = $action;

                    $this->replaceActionHistory($action, $users['safety_manager'][($departmentTaskCounter + 2) % count($users['safety_manager'])]['id']);
                    $this->upsertActionFollowUp($action, $followUp, $departmentTaskCounter);

                    $this->upsertComparisonReport(
                        $organization,
                        $baseline,
                        $followUp,
                        $action,
                        $users['external_reviewer'][($departmentTaskCounter + 1) % count($users['external_reviewer'])]['id'],
                        (string) $spec['key'],
                        (string) $taskSpec['code'],
                        $departmentTaskCounter,
                    );

                    if ($departmentTaskCounter % 4 === 0) {
                        $this->upsertRejectedRecommendation(
                            $organization,
                            $baseline,
                            $users['safety_manager'][($departmentTaskCounter + 1) % count($users['safety_manager'])],
                            (string) $spec['key'],
                            (string) $taskSpec['code'],
                            $task,
                        );
                    }

                    $departmentTaskCounter++;
                }
            }
        }

        return [
            'tasks' => $tasks,
            'baselines' => $baselines,
            'followups' => $followUps,
            'actions' => $actions,
        ];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $spec
     * @param array{tasks:list<array<string,mixed>>,baselines:list<array<string,mixed>>,followups:list<array<string,mixed>>,actions:list<array<string,mixed>>} $workflow
     * @param array<string, list<array<string, mixed>>> $users
     */
    private function seedFeedback(array $organization, array $spec, array $workflow, array $users): void
    {
        foreach ($workflow['tasks'] as $index => $task) {
            $baseline = $workflow['baselines'][$index];
            $followUp = $workflow['followups'][$index];

            for ($offset = 0; $offset < 2; $offset++) {
                $worker = $users['worker'][($index * 2 + $offset) % count($users['worker'])];
                $this->upsertWorkerFeedback(
                    $organization,
                    $task,
                    $followUp,
                    $worker,
                    (string) $spec['key'],
                    $index,
                    $offset,
                );
            }

            $supervisor = $users['supervisor'][$index % count($users['supervisor'])];
            $this->upsertSupervisorFeedback(
                $organization,
                $task,
                $baseline,
                $supervisor,
                (string) $spec['key'],
                $index,
            );
        }
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $worksiteSpec
     * @return array{id:int,uuid:string,name:string,code:string}
     */
    private function upsertWorksite(array $organization, string $orgKey, array $worksiteSpec, int $index): array
    {
        $uuid = $this->uuid($orgKey, 'worksite', (string) $worksiteSpec['code']);
        $existing = $this->db->fetchAssociative('SELECT id FROM worksites WHERE uuid = ?', [$uuid]);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'name' => $worksiteSpec['name'],
            'status' => 'active',
            'location' => $worksiteSpec['location'],
            'updated_at' => $this->timestamp($index),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('worksites', $data + ['created_at' => $this->timestamp($index)]);
            return ['id' => (int) $this->db->lastInsertId(), 'uuid' => $uuid, 'name' => $worksiteSpec['name'], 'code' => $worksiteSpec['code']];
        }

        $this->db->update('worksites', $data, ['id' => (int) $existing['id']]);

        return ['id' => (int) $existing['id'], 'uuid' => $uuid, 'name' => $worksiteSpec['name'], 'code' => $worksiteSpec['code']];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array{id:int,uuid:string,name:string,code:string} $worksite
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $worksiteSpec
     */
    private function upsertPilotSite(array $organization, array $worksite, array $spec, array $worksiteSpec, int $index): void
    {
        $uuid = $this->uuid((string) $spec['key'], 'pilot-site', (string) $worksiteSpec['code']);
        $existing = $this->db->fetchAssociative('SELECT id FROM pilot_sites WHERE uuid = ? OR (organization_id = ? AND worksite_id = ?)', [$uuid, $organization['id'], $worksite['id']]);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'worksite_id' => $worksite['id'],
            'worksite_uuid' => $worksite['uuid'],
            'enrollment_date' => (new DateTimeImmutable((string) $spec['subscription_start']))->modify('+' . $index . ' day')->format('Y-m-d'),
            'pilot_status' => 'enrolled',
            'target_worker_count' => $worksiteSpec['target_worker_count'],
            'actual_worker_count' => $worksiteSpec['actual_worker_count'],
            'industry' => $spec['industry'],
            'notes' => $worksiteSpec['notes'],
            'updated_at' => $this->timestamp($index),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('pilot_sites', $data + ['created_at' => $this->timestamp($index)]);
            return;
        }

        $this->db->update('pilot_sites', $data, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array{id:int,uuid:string,name:string,code:string} $worksite
     * @param array<string, mixed> $departmentSpec
     * @return array{id:int,uuid:string,name:string,code:string,worksite_id:int,parent_department_id:?int}
     */
    private function upsertDepartment(array $organization, array $worksite, string $orgKey, array $departmentSpec, ?int $parentId, int $index): array
    {
        $uuid = $this->uuid($orgKey, 'department', (string) $worksite['code'], (string) $departmentSpec['code']);
        $existing = $this->db->fetchAssociative('SELECT id FROM departments WHERE uuid = ?', [$uuid]);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'worksite_id' => $worksite['id'],
            'parent_department_id' => $parentId,
            'name' => $departmentSpec['name'],
            'status' => 'active',
            'updated_at' => $this->timestamp($index),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('departments', $data + ['created_at' => $this->timestamp($index)]);
            return [
                'id' => (int) $this->db->lastInsertId(),
                'uuid' => $uuid,
                'name' => $departmentSpec['name'],
                'code' => $departmentSpec['code'],
                'worksite_id' => $worksite['id'],
                'parent_department_id' => $parentId,
            ];
        }

        $this->db->update('departments', $data, ['id' => (int) $existing['id']]);

        return [
            'id' => (int) $existing['id'],
            'uuid' => $uuid,
            'name' => $departmentSpec['name'],
            'code' => $departmentSpec['code'],
            'worksite_id' => $worksite['id'],
            'parent_department_id' => $parentId,
        ];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array{id:int,uuid:string,name:string,code:string,worksite_id:int,parent_department_id:?int} $department
     * @return array{id:int,uuid:string,name:string,code:string,department_id:int}
     */
    private function upsertJobRole(array $organization, array $department, string $orgKey, string $roleCode, string $roleName): array
    {
        $uuid = $this->uuid($orgKey, 'job-role', (string) $department['code'], $roleCode);
        $existing = $this->db->fetchAssociative('SELECT id FROM job_roles WHERE uuid = ?', [$uuid]);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'department_id' => $department['id'],
            'name' => $roleName,
            'status' => 'active',
            'updated_at' => $this->now(),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('job_roles', $data + ['created_at' => $this->now()]);
            return ['id' => (int) $this->db->lastInsertId(), 'uuid' => $uuid, 'name' => $roleName, 'code' => $roleCode, 'department_id' => $department['id']];
        }

        $this->db->update('job_roles', $data, ['id' => (int) $existing['id']]);

        return ['id' => (int) $existing['id'], 'uuid' => $uuid, 'name' => $roleName, 'code' => $roleCode, 'department_id' => $department['id']];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array{id:int,uuid:string,name:string,code:string} $worksite
     * @param array{id:int,uuid:string,name:string,code:string,worksite_id:int,parent_department_id:?int} $department
     * @param array{id:int,uuid:string,name:string,code:string,department_id:int}|null $jobRole
     * @param array<string, mixed> $taskSpec
     * @return array{id:int,uuid:string,name:string,code:string,model:string,worksite:array<string,mixed>,department:array<string,mixed>,job_role:?array<string,mixed>}
     */
    private function upsertTask(array $organization, array $worksite, array $department, ?array $jobRole, string $orgKey, array $taskSpec): array
    {
        $uuid = $this->uuid($orgKey, 'task', (string) $taskSpec['code']);
        $existing = $this->db->fetchAssociative('SELECT id FROM tasks WHERE uuid = ?', [$uuid]);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'worksite_id' => $worksite['id'],
            'department_id' => $department['id'],
            'job_role_id' => $jobRole['id'] ?? null,
            'name' => $taskSpec['name'],
            'assessment_model' => strtolower((string) $taskSpec['model']),
            'task_code' => $taskSpec['task_code'],
            'status' => 'active',
            'description' => $this->taskDescription((string) $taskSpec['name'], (string) $department['name'], (string) $worksite['name']),
            'updated_at' => $this->now(),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('tasks', $data + ['created_at' => $this->now()]);
            $id = (int) $this->db->lastInsertId();
        } else {
            $this->db->update('tasks', $data, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        }

        return [
            'id' => $id,
            'uuid' => $uuid,
            'name' => $taskSpec['name'],
            'code' => $taskSpec['code'],
            'model' => strtolower((string) $taskSpec['model']),
            'worksite' => $worksite,
            'department' => $department,
            'job_role' => $jobRole,
        ];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $task
     * @param array<string, mixed> $reviewer
     * @param array<string, mixed> $assessmentData
     * @return array{id:int,uuid:string,status:string,is_baseline:bool,final_score:array<string,mixed>,initial_score:array<string,mixed>,task:array<string,mixed>}
     */
    private function upsertAssessment(array $organization, array $task, int $createdBy, array $reviewer, string $orgKey, string $assessmentCode, string $status, bool $isBaseline, array $assessmentData, int $minutesOffset): array
    {
        $uuid = $this->uuid($orgKey, 'assessment', $assessmentCode);
        $existing = $this->db->fetchAssociative('SELECT id, created_at FROM assessments WHERE uuid = ?', [$uuid]);
        $createdAt = $this->timestamp(0, $minutesOffset);
        $data = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'task_id' => $task['id'],
            'task_uuid' => $task['uuid'],
            'model' => $task['model'],
            'metrics_json' => json_encode($assessmentData['metrics'], JSON_THROW_ON_ERROR),
            'initial_score_json' => json_encode($assessmentData['initial_score'], JSON_THROW_ON_ERROR),
            'final_score_json' => $assessmentData['final_score'] === null ? null : json_encode($assessmentData['final_score'], JSON_THROW_ON_ERROR),
            'status' => $status,
            'is_baseline' => $isBaseline ? 1 : 0,
            'score_source' => $assessmentData['score_source'],
            'created_by' => $createdBy,
            'reviewer_id' => in_array($status, ['reviewed', 'locked', 'flagged'], true) ? $reviewer['id'] : null,
            'reviewer_name' => in_array($status, ['reviewed', 'locked', 'flagged'], true) ? $reviewer['full_name'] : null,
            'reviewer_credentials' => in_array($status, ['reviewed', 'locked', 'flagged'], true) ? $this->reviewerCredentials($reviewer['role_slug']) : null,
            'reviewer_notes' => $assessmentData['reviewer_notes'],
            'adjustment_reason' => $assessmentData['adjustment_reason'],
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('assessments', $data + ['created_at' => $createdAt]);
            $id = (int) $this->db->lastInsertId();
        } else {
            $this->db->update('assessments', $data, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        }

        $this->replaceAssessmentEvidence($id, $assessmentData['risk_factors'], $assessmentData['body_regions'], $createdAt);

        return [
            'id' => $id,
            'uuid' => $uuid,
            'status' => $status,
            'is_baseline' => $isBaseline,
            'final_score' => $assessmentData['final_score'] ?? [],
            'initial_score' => $assessmentData['initial_score'],
            'task' => $task,
        ];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $assessment
     * @param array<string, mixed> $reviewer
     * @param array<string, mixed> $data
     * @return array{id:int,uuid:string,status:string,title:string}
     */
    private function upsertRecommendation(array $organization, array $assessment, array $reviewer, string $orgKey, string $taskCode, array $data): array
    {
        $uuid = $this->uuid($orgKey, 'recommendation', $taskCode, 'primary');
        $existing = $this->db->fetchAssociative('SELECT id FROM corrective_action_recommendations WHERE uuid = ?', [$uuid]);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'assessment_uuid' => $assessment['uuid'],
            'library_item_uuid' => null,
            'control_code' => $data['control_code'],
            'title' => $data['title'],
            'description' => $data['description'],
            'reason' => $data['reason'],
            'hierarchy_level' => $data['hierarchy_level'],
            'control_type' => $data['control_type'],
            'priority' => $data['priority'],
            'rank_order' => 1,
            'expected_risk_reduction_pct' => $data['expected_risk_reduction_pct'],
            'due_days' => $data['due_days'],
            'follow_up_days' => $data['follow_up_days'],
            'status' => 'accepted',
            'evidence_json' => json_encode($data['evidence'], JSON_THROW_ON_ERROR),
            'reject_reason' => null,
            'reviewed_at' => $this->now(),
            'reviewed_by' => $reviewer['id'],
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->db->insert('corrective_action_recommendations', $payload + ['created_at' => $this->now()]);
            $id = (int) $this->db->lastInsertId();
        } else {
            $this->db->update('corrective_action_recommendations', $payload, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        }

        return ['id' => $id, 'uuid' => $uuid, 'status' => 'accepted', 'title' => $data['title']];
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $assessment
     * @param array<string, mixed> $reviewer
     * @param array<string, mixed> $task
     */
    private function upsertRejectedRecommendation(array $organization, array $assessment, array $reviewer, string $orgKey, string $taskCode, array $task): void
    {
        $uuid = $this->uuid($orgKey, 'recommendation', $taskCode, 'secondary');
        $existing = $this->db->fetchAssociative('SELECT id FROM corrective_action_recommendations WHERE uuid = ?', [$uuid]);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'assessment_uuid' => $assessment['uuid'],
            'library_item_uuid' => null,
            'control_code' => strtoupper(substr($task['code'], 0, 3)) . '-ALT',
            'title' => 'Escalate task scheduling review for ' . $task['name'],
            'description' => 'Alternative recommendation held back pending staffing and throughput review.',
            'reason' => 'Exposure can also be reduced by revising staffing cadence, but the current throughput plan cannot absorb the change this cycle.',
            'hierarchy_level' => 'administrative',
            'control_type' => 'process',
            'priority' => 'medium',
            'rank_order' => 2,
            'expected_risk_reduction_pct' => 18.00,
            'due_days' => 21,
            'follow_up_days' => 14,
            'status' => 'rejected',
            'evidence_json' => json_encode(['required' => ['document'], 'rationale' => 'Needs staffing model review before adoption.'], JSON_THROW_ON_ERROR),
            'reject_reason' => 'Deferred until the next staffing and throughput balancing cycle.',
            'reviewed_at' => $this->now(),
            'reviewed_by' => $reviewer['id'],
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->db->insert('corrective_action_recommendations', $payload + ['created_at' => $this->now()]);
            return;
        }

        $this->db->update('corrective_action_recommendations', $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $assessment
     * @param array<string, mixed> $recommendation
     * @param array<string, mixed> $assignee
     * @param array<string, mixed> $assigner
     * @param array<string, mixed> $data
     * @return array{id:int,uuid:string,status:string,title:string,due_date:string}
     */
    private function upsertCorrectiveAction(array $organization, array $assessment, array $recommendation, array $assignee, array $assigner, string $orgKey, string $taskCode, array $data): array
    {
        $uuid = $this->uuid($orgKey, 'action', $taskCode);
        $existing = $this->db->fetchAssociative('SELECT id FROM corrective_actions WHERE uuid = ?', [$uuid]);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'assessment_uuid' => $assessment['uuid'],
            'recommendation_uuid' => $recommendation['uuid'],
            'library_item_uuid' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'reason' => $data['reason'],
            'control_type' => $data['control_type'],
            'hierarchy_level' => $data['hierarchy_level'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'assigned_to_user_id' => $assignee['id'],
            'assigned_by_user_id' => $assigner['id'],
            'due_date' => $data['due_date'],
            'follow_up_assessment_due_date' => $data['follow_up_due_date'],
            'evidence_requirements_json' => json_encode($data['evidence_requirements'], JSON_THROW_ON_ERROR),
            'reject_reason' => $data['reject_reason'],
            'completed_at' => $data['completed_at'],
            'verified_at' => $data['verified_at'],
            'rejected_at' => $data['rejected_at'],
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->db->insert('corrective_actions', $payload + ['created_at' => $this->now()]);
            $id = (int) $this->db->lastInsertId();
        } else {
            $this->db->update('corrective_actions', $payload, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        }

        return ['id' => $id, 'uuid' => $uuid, 'status' => $data['status'], 'title' => $data['title'], 'due_date' => $data['due_date']];
    }

    /**
     * @param array<string, mixed> $action
     */
    private function replaceActionHistory(array $action, int $actorId): void
    {
        $this->db->delete('corrective_action_status_history', ['action_uuid' => $action['uuid']]);

        $statuses = match ($action['status']) {
            'verified' => ['assigned', 'in_progress', 'completed', 'verified'],
            'completed' => ['assigned', 'in_progress', 'completed'],
            'in_progress' => ['assigned', 'in_progress'],
            'overdue' => ['assigned', 'overdue'],
            default => ['assigned'],
        };

        foreach ($statuses as $index => $status) {
            $this->db->insert('corrective_action_status_history', [
                'action_uuid' => $action['uuid'],
                'status' => $status,
                'actor_id' => $actorId,
                'notes' => $this->statusNote($status),
                'created_at' => $this->timestamp($index, $index * 45),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $followUpAssessment
     */
    private function upsertActionFollowUp(array $action, array $followUpAssessment, int $index): void
    {
        $existing = $this->db->fetchAssociative('SELECT id FROM corrective_action_follow_ups WHERE action_uuid = ?', [$action['uuid']]);
        $status = in_array($action['status'], ['completed', 'verified'], true) ? 'completed' : 'scheduled';
        $payload = [
            'action_uuid' => $action['uuid'],
            'due_date' => (new DateTimeImmutable($action['due_date']))->modify('+14 days')->format('Y-m-d'),
            'follow_up_assessment_uuid' => in_array($status, ['completed'], true) ? $followUpAssessment['uuid'] : null,
            'status' => $status,
            'updated_at' => $this->timestamp($index, 120),
        ];

        if ($existing === false) {
            $this->db->insert('corrective_action_follow_ups', $payload + ['created_at' => $this->timestamp($index, 90)]);
            return;
        }

        $this->db->update('corrective_action_follow_ups', $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $followUp
     * @param array<string, mixed> $action
     */
    private function upsertComparisonReport(array $organization, array $baseline, array $followUp, array $action, int $generatedBy, string $orgKey, string $taskCode, int $index): void
    {
        $uuid = $this->uuid($orgKey, 'comparison', $taskCode);
        $existing = $this->db->fetchAssociative('SELECT id FROM comparison_reports WHERE uuid = ?', [$uuid]);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'baseline_assessment_uuid' => $baseline['uuid'],
            'follow_up_assessment_uuid' => $followUp['uuid'],
            'corrective_action_uuid' => $action['uuid'],
            'model' => $baseline['task']['model'],
            'baseline_score_json' => json_encode($baseline['final_score'] ?: $baseline['initial_score'], JSON_THROW_ON_ERROR),
            'follow_up_score_json' => json_encode($followUp['final_score'] ?: $followUp['initial_score'], JSON_THROW_ON_ERROR),
            'score_diff_json' => json_encode($this->scoreDiff($baseline, $followUp), JSON_THROW_ON_ERROR),
            'risk_reduction_percent' => $this->riskReduction($baseline, $followUp),
            'direction' => 'improved',
            'body_regions_improved_json' => json_encode($this->bodyRegionNames($baseline, $followUp, true), JSON_THROW_ON_ERROR),
            'body_regions_worsened_json' => json_encode([], JSON_THROW_ON_ERROR),
            'evidence_chain_json' => json_encode([
                'baselineStatus' => $baseline['status'],
                'followUpStatus' => $followUp['status'],
                'correctiveActionUuid' => $action['uuid'],
                'comparisonNarrative' => 'Post-control follow-up shows lower ergonomic exposure than the locked baseline assessment.',
            ], JSON_THROW_ON_ERROR),
            'status' => $index % 3 === 0 ? 'locked' : 'generated',
            'generated_by' => $generatedBy,
            'generated_at' => $this->timestamp($index, 180),
            'locked_at' => $index % 3 === 0 ? $this->timestamp($index, 210) : null,
            'updated_at' => $this->now(),
        ];

        if ($existing === false) {
            $this->db->insert('comparison_reports', $payload + ['created_at' => $this->timestamp($index, 180)]);
            return;
        }

        $this->db->update('comparison_reports', $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $task
     * @param array<string, mixed> $assessment
     * @param array<string, mixed> $worker
     */
    private function upsertWorkerFeedback(array $organization, array $task, array $assessment, array $worker, string $orgKey, int $taskIndex, int $offset): void
    {
        $uuid = $this->uuid($orgKey, 'worker-feedback', $task['code'], (string) $offset);
        $existing = $this->db->fetchAssociative('SELECT id FROM worker_feedback WHERE uuid = ?', [$uuid]);
        $bodyRegion = $this->feedbackBodyRegion($taskIndex + $offset);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'task_id' => $task['id'],
            'task_uuid' => $task['uuid'],
            'assessment_uuid' => $assessment['uuid'],
            'worksite_id' => $task['worksite']['id'],
            'worksite_uuid' => $task['worksite']['uuid'],
            'department_id' => $task['department']['id'],
            'department_uuid' => $task['department']['uuid'],
            'job_role_id' => $task['job_role']['id'] ?? $worker['job_role']['id'] ?? null,
            'job_role_uuid' => $task['job_role']['uuid'] ?? $worker['job_role']['uuid'] ?? null,
            'submitted_by_user_id' => $worker['id'],
            'anonymous_status' => $offset === 1 ? 1 : 0,
            'body_region' => $bodyRegion,
            'has_discomfort' => 1,
            'discomfort_level' => 2 + (($taskIndex + $offset) % 3),
            'frequency_level' => 2 + (($taskIndex + $offset) % 2),
            'difficulty_level' => 2 + (($taskIndex + 1) % 3),
            'reporting_comfort_level' => 4,
            'pain_7_day_level' => 2 + (($taskIndex + $offset) % 3),
            'pain_30_day_level' => 3 + (($taskIndex + $offset) % 2),
            'suggested_change' => 'Lower the lift height, keep supplies closer to the point of use, and rebalance task rotation at peak volume.',
            'metadata_json' => json_encode([
                'submittedFrom' => 'production_seed',
                'taskPhase' => $offset === 0 ? 'baseline' : 'follow_up',
                'confidence' => 'high',
            ], JSON_THROW_ON_ERROR),
            'updated_at' => $this->timestamp($taskIndex, 240 + ($offset * 12)),
        ];

        if ($existing === false) {
            $this->db->insert('worker_feedback', $payload + ['created_at' => $this->timestamp($taskIndex, 240 + ($offset * 12))]);
            return;
        }

        $this->db->update('worker_feedback', $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $task
     * @param array<string, mixed> $assessment
     * @param array<string, mixed> $supervisor
     */
    private function upsertSupervisorFeedback(array $organization, array $task, array $assessment, array $supervisor, string $orgKey, int $taskIndex): void
    {
        $uuid = $this->uuid($orgKey, 'supervisor-feedback', $task['code']);
        $existing = $this->db->fetchAssociative('SELECT id FROM supervisor_feedback WHERE uuid = ?', [$uuid]);
        $payload = [
            'uuid' => $uuid,
            'organization_id' => $organization['id'],
            'organization_uuid' => $organization['uuid'],
            'task_id' => $task['id'],
            'task_uuid' => $task['uuid'],
            'assessment_uuid' => $assessment['uuid'],
            'worksite_id' => $task['worksite']['id'],
            'worksite_uuid' => $task['worksite']['uuid'],
            'department_id' => $task['department']['id'],
            'department_uuid' => $task['department']['uuid'],
            'job_role_id' => $task['job_role']['id'] ?? $supervisor['job_role']['id'] ?? null,
            'job_role_uuid' => $task['job_role']['uuid'] ?? $supervisor['job_role']['uuid'] ?? null,
            'submitted_by_user_id' => $supervisor['id'],
            'body_region' => $this->feedbackBodyRegion($taskIndex),
            'observed_risk_level' => ['medium', 'high', 'medium', 'high'][$taskIndex % 4],
            'observed_issue_type' => ['awkward_posture', 'forceful_exertion', 'repetition', 'manual_handling'][$taskIndex % 4],
            'frequency_level' => 2 + ($taskIndex % 3),
            'severity_level' => 2 + (($taskIndex + 1) % 3),
            'suggested_change' => 'Adjust workstation presentation height and reinforce pacing controls during the highest-output interval.',
            'notes' => 'Supervisor observation confirms the task spikes during peak throughput windows and improves when staging is prepared in advance.',
            'updated_at' => $this->timestamp($taskIndex, 260),
        ];

        if ($existing === false) {
            $this->db->insert('supervisor_feedback', $payload + ['created_at' => $this->timestamp($taskIndex, 260)]);
            return;
        }

        $this->db->update('supervisor_feedback', $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function upsertUser(array $columns): int
    {
        $existing = $this->db->fetchAssociative('SELECT id FROM users WHERE email = ? OR uuid = ?', [$columns['email'], $columns['uuid']]);
        $data = $columns + ['updated_at' => $this->now(), 'deleted_at' => null, 'last_login_at' => null, 'otp_enabled' => 0, 'authz_version' => 1, 'totp_secret_encrypted' => null, 'recovery_codes_json' => null];

        if ($existing === false) {
            $this->db->insert('users', $data + ['created_at' => $this->now()]);
            return (int) $this->db->lastInsertId();
        }

        $this->db->update('users', $data, ['id' => (int) $existing['id']]);
        return (int) $existing['id'];
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function upsertProfile(int $userId, array $columns): void
    {
        $existing = $this->db->fetchAssociative('SELECT id FROM user_profiles WHERE user_id = ? OR uuid = ?', [$userId, $columns['uuid']]);
        $data = $columns + ['user_id' => $userId, 'updated_at' => $this->now()];

        if ($existing === false) {
            $this->db->insert('user_profiles', $data + ['created_at' => $this->now()]);
            return;
        }

        $this->db->update('user_profiles', $data, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array{id:int,uuid:string} $organization
     * @param array<string, mixed> $columns
     */
    private function upsertMembership(int $userId, array $organization, array $columns): int
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM organization_memberships WHERE user_id = ? AND organization_id = ?',
            [$userId, $organization['id']],
        );
        $data = $columns + [
            'user_id' => $userId,
            'organization_id' => $organization['id'],
            'status' => 'active',
            'is_primary' => 1,
            'updated_at' => $this->now(),
            'deleted_at' => null,
        ];

        if ($existing === false) {
            $this->db->insert('organization_memberships', $data + ['created_at' => $this->now()]);
            return (int) $this->db->lastInsertId();
        }

        $this->db->update('organization_memberships', $data, ['id' => (int) $existing['id']]);
        return (int) $existing['id'];
    }

    /**
     * @param list<string> $riskFactors
     * @param list<array<string, mixed>> $bodyRegions
     */
    private function replaceAssessmentEvidence(int $assessmentId, array $riskFactors, array $bodyRegions, string $createdAt): void
    {
        $this->db->delete('assessment_risk_factors', ['assessment_id' => $assessmentId]);
        foreach ($riskFactors as $factor) {
            $this->db->insert('assessment_risk_factors', [
                'assessment_id' => $assessmentId,
                'factor_key' => $factor,
                'created_at' => $createdAt,
            ]);
        }

        $this->db->delete('assessment_body_regions', ['assessment_id' => $assessmentId]);
        foreach ($bodyRegions as $region) {
            $this->db->insert('assessment_body_regions', [
                'assessment_id' => $assessmentId,
                'region' => $region['region'],
                'side' => $region['side'],
                'intensity' => $region['intensity'],
                'created_at' => $createdAt,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function loadRoleIds(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT id, name FROM iam_roles');
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['name']] = (int) $row['id'];
        }

        foreach (array_keys(self::ROLE_COUNTS) as $roleSlug) {
            if (!isset($map[$roleSlug])) {
                throw new RuntimeException(sprintf('Required role "%s" was not found. Run the customer role seeders first.', $roleSlug));
            }
        }

        return $map;
    }

    private function ensureSystemRole(string $slug, string $label, string $now): int
    {
        $existing = $this->db->fetchAssociative('SELECT id FROM iam_roles WHERE name = ?', [$slug]);
        if ($existing !== false) {
            return (int) $existing['id'];
        }

        $this->db->insert('iam_roles', [
            'uuid' => $this->uuid('system', 'role', $slug),
            'name' => $slug,
            'label' => $label,
            'scope' => 'system',
            'is_system' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function roleId(string $slug): int
    {
        return $this->roleIds[$slug] ?? throw new RuntimeException(sprintf('Role "%s" was not loaded.', $slug));
    }

    /**
     * @param list<array<string, mixed>> $worksites
     * @param list<array<string, mixed>> $departments
     * @param list<array<string, mixed>> $jobRoles
     * @return array{worksite:?array<string,mixed>,department:?array<string,mixed>,job_role:?array<string,mixed>}
     */
    private function membershipScope(string $roleSlug, int $index, array $worksites, array $departments, array $jobRoles): array
    {
        if ($roleSlug === 'organization_admin' || $roleSlug === 'external_reviewer') {
            return ['worksite' => null, 'department' => null, 'job_role' => null];
        }

        if ($roleSlug === 'safety_manager') {
            return ['worksite' => $worksites[($index - 1) % count($worksites)], 'department' => null, 'job_role' => null];
        }

        if ($roleSlug === 'supervisor') {
            $department = $departments[($index - 1) % count($departments)];
            $jobRole = $this->firstRoleForDepartment($jobRoles, (int) $department['id']);
            $worksite = $this->firstWorksiteForDepartment($worksites, (int) $department['worksite_id']);
            return ['worksite' => $worksite, 'department' => $department, 'job_role' => $jobRole];
        }

        $jobRole = $jobRoles[($index - 1) % count($jobRoles)];
        $department = $this->firstDepartmentForRole($departments, (int) $jobRole['department_id']);
        $worksite = $this->firstWorksiteForDepartment($worksites, (int) $department['worksite_id']);

        return ['worksite' => $worksite, 'department' => $department, 'job_role' => $jobRole];
    }

    /**
     * @param list<array<string, mixed>> $jobRoles
     */
    private function firstRoleForDepartment(array $jobRoles, int $departmentId): ?array
    {
        foreach ($jobRoles as $jobRole) {
            if ((int) $jobRole['department_id'] === $departmentId) {
                return $jobRole;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $departments
     */
    private function firstDepartmentForRole(array $departments, int $departmentId): array
    {
        foreach ($departments as $department) {
            if ((int) $department['id'] === $departmentId) {
                return $department;
            }
        }

        throw new RuntimeException('Department not found for seeded role scope.');
    }

    /**
     * @param list<array<string, mixed>> $worksites
     */
    private function firstWorksiteForDepartment(array $worksites, int $worksiteId): array
    {
        foreach ($worksites as $worksite) {
            if ((int) $worksite['id'] === $worksiteId) {
                return $worksite;
            }
        }

        throw new RuntimeException('Worksite not found for seeded department scope.');
    }

    /**
     * @param array<string, array<string, mixed>> $jobRoles
     */
    private function firstTaskJobRole(array $jobRoles, string $departmentKey): ?array
    {
        foreach ($jobRoles as $jobKey => $jobRole) {
            if (str_starts_with($jobKey, $departmentKey . ':')) {
                return $jobRole;
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function personDetails(string $orgKey, string $roleSlug, int $index): array
    {
        $seed = abs(crc32($orgKey . ':' . $roleSlug . ':' . $index));
        $first = self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[($seed >> 3) % count(self::LAST_NAMES)];
        $phone = '+2348' . str_pad((string) (($seed % 900000000) + 100000000), 9, '0', STR_PAD_LEFT);
        $employeeId = strtoupper(substr($orgKey, 0, 3)) . '-' . strtoupper(substr($roleSlug, 0, 3)) . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        return [trim($first . ' ' . $last), $phone, $employeeId];
    }

    private function personEmail(string $fullName, string $domain, string $roleSlug, int $index): string
    {
        $local = strtolower(str_replace(' ', '.', preg_replace('/[^A-Za-z ]/', '', $fullName) ?: 'user'));
        return sprintf('%s.%s%d@%s', $local, str_replace('_', '', $roleSlug), $index, $domain);
    }

    private function organizationEmailDomain(string $slug): string
    {
        $plain = str_replace('-', '', $slug);
        return $plain . '.internal';
    }

    private function jobTitleForRole(string $roleSlug, int $index): string
    {
        return match ($roleSlug) {
            'organization_admin' => 'Organization Administrator',
            'safety_manager' => 'Safety Manager ' . $index,
            'supervisor' => 'Operations Supervisor ' . $index,
            'external_reviewer' => 'External Ergonomics Reviewer ' . $index,
            'worker' => 'Operations Worker ' . $index,
            default => 'Team Member',
        };
    }

    private function reviewerCredentials(string $roleSlug): string
    {
        return $roleSlug === 'external_reviewer' ? 'CPE, Ergonomics Reviewer' : 'Safety Lead';
    }

    private function taskDescription(string $taskName, string $departmentName, string $worksiteName): string
    {
        return sprintf(
            'Operational task "%s" in %s at %s, seeded with realistic workflow and ergonomic review history for production-style environment bring-up.',
            $taskName,
            $departmentName,
            $worksiteName,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineAssessmentData(array $task, int $index): array
    {
        $raw = 8 + ($index % 4);
        return [
            'metrics' => $this->metricsForTask($task, $raw, false),
            'initial_score' => $this->scoreForTask($task['model'], $raw, 'high'),
            'final_score' => $this->scoreForTask($task['model'], $raw, 'high'),
            'score_source' => 'reviewer_confirmed',
            'risk_factors' => $this->riskFactorsForTask($task, true),
            'body_regions' => $this->bodyRegionsForTask($task, true),
            'reviewer_notes' => 'Baseline assessment reviewed and locked prior to control implementation.',
            'adjustment_reason' => 'Reviewer normalized the baseline to reflect sustained exposure across the full shift.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpAssessmentData(array $task, int $index): array
    {
        $raw = max(3, 6 + ($index % 3));
        return [
            'metrics' => $this->metricsForTask($task, $raw, true),
            'initial_score' => $this->scoreForTask($task['model'], $raw + 1, 'medium'),
            'final_score' => $this->scoreForTask($task['model'], $raw, 'medium'),
            'score_source' => 'reviewer_confirmed',
            'risk_factors' => $this->riskFactorsForTask($task, false),
            'body_regions' => $this->bodyRegionsForTask($task, false),
            'reviewer_notes' => 'Follow-up assessment reflects the post-control task method after workstation and staging changes.',
            'adjustment_reason' => 'Reviewer adjusted the follow-up score after confirming lower repetition and reduced reach depth.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentAssessmentData(array $task, int $index): array
    {
        $raw = 6 + ($index % 4);
        return [
            'metrics' => $this->metricsForTask($task, $raw, false),
            'initial_score' => $this->scoreForTask($task['model'], $raw, 'medium'),
            'final_score' => null,
            'score_source' => 'manual',
            'risk_factors' => $this->riskFactorsForTask($task, false),
            'body_regions' => $this->bodyRegionsForTask($task, false),
            'reviewer_notes' => $index % 2 === 0 ? 'Submitted for reviewer validation.' : null,
            'adjustment_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationData(array $task, int $index): array
    {
        $controls = [
            [
                'control_code' => 'ENG-LIFT-01',
                'title' => 'Reposition material presentation height for ' . $task['name'],
                'description' => 'Move primary input materials closer to waist height and reduce deep forward reach during the task cycle.',
                'reason' => 'Baseline assessment showed repeated trunk flexion and reach distance driving higher ergonomic strain.',
                'hierarchy_level' => 'engineering',
                'control_type' => 'workstation',
                'priority' => 'high',
                'expected_risk_reduction_pct' => 28.00,
                'due_days' => 21,
                'follow_up_days' => 14,
                'evidence' => ['required' => ['photo', 'document'], 'worker_feedback_expected' => true],
            ],
            [
                'control_code' => 'ADM-ROT-02',
                'title' => 'Rebalance task rotation and break pacing for ' . $task['name'],
                'description' => 'Introduce rotation intervals and peak-volume pacing controls so exposure does not accumulate in one role segment.',
                'reason' => 'Observed exposure pattern was concentrated in one work cycle without enough recovery time.',
                'hierarchy_level' => 'administrative',
                'control_type' => 'process',
                'priority' => 'medium',
                'expected_risk_reduction_pct' => 20.00,
                'due_days' => 14,
                'follow_up_days' => 14,
                'evidence' => ['required' => ['document', 'worker_feedback'], 'worker_feedback_expected' => true],
            ],
        ];

        return $controls[$index % count($controls)];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionData(int $index): array
    {
        $statusSet = [
            ['status' => 'assigned', 'completed_at' => null, 'verified_at' => null, 'rejected_at' => null, 'reject_reason' => null],
            ['status' => 'in_progress', 'completed_at' => null, 'verified_at' => null, 'rejected_at' => null, 'reject_reason' => null],
            ['status' => 'completed', 'completed_at' => $this->timestamp($index, 144), 'verified_at' => null, 'rejected_at' => null, 'reject_reason' => null],
            ['status' => 'verified', 'completed_at' => $this->timestamp($index, 144), 'verified_at' => $this->timestamp($index, 168), 'rejected_at' => null, 'reject_reason' => null],
            ['status' => 'overdue', 'completed_at' => null, 'verified_at' => null, 'rejected_at' => null, 'reject_reason' => null],
        ][$index % 5];

        return [
            'title' => 'Implement approved ergonomic control package',
            'description' => 'Carry out the accepted corrective change, confirm operator handoff, and update the prevention trail with implementation notes.',
            'reason' => 'Action issued from accepted recommendation after baseline review confirmed elevated exposure.',
            'control_type' => $index % 2 === 0 ? 'workstation' : 'process',
            'hierarchy_level' => $index % 2 === 0 ? 'engineering' : 'administrative',
            'priority' => $index % 3 === 0 ? 'high' : 'medium',
            'status' => $statusSet['status'],
            'due_date' => (new DateTimeImmutable($this->timestamp($index, 96)))->format('Y-m-d'),
            'follow_up_due_date' => (new DateTimeImmutable($this->timestamp($index, 96)))->modify('+21 days')->format('Y-m-d'),
            'evidence_requirements' => ['photo', 'document', 'worker_feedback'],
            'reject_reason' => $statusSet['reject_reason'],
            'completed_at' => $statusSet['completed_at'],
            'verified_at' => $statusSet['verified_at'],
            'rejected_at' => $statusSet['rejected_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsForTask(array $task, int $rawScore, bool $improved): array
    {
        return [
            'cycle_time_seconds' => $improved ? 54 : 47,
            'lift_frequency_per_minute' => max(1, 6 - ($improved ? 2 : 0)),
            'peak_reach_cm' => $improved ? 38 : 56,
            'trunk_flexion_degrees' => $improved ? 20 : 38,
            'shoulder_elevation_degrees' => $task['model'] === 'rula' ? ($improved ? 34 : 51) : ($improved ? 18 : 28),
            'handling_force_kg' => $task['model'] === 'reba' ? ($improved ? 8 : 13) : ($improved ? 4 : 7),
            'task_model' => $task['model'],
            'task_code' => $task['code'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreForTask(string $model, int $rawScore, string $riskLevel): array
    {
        $normalized = round(min(100, $rawScore * ($model === 'reba' ? 7.5 : 8.5)), 2);
        $category = match ($riskLevel) {
            'high' => 'high',
            'medium' => 'moderate',
            default => 'low',
        };

        return [
            'raw_score' => $rawScore,
            'normalized_score' => $normalized,
            'risk_level' => $riskLevel,
            'risk_category' => $category,
            'algorithm_version' => 'seed-v1',
        ];
    }

    /**
     * @return list<string>
     */
    private function riskFactorsForTask(array $task, bool $baseline): array
    {
        $factors = $task['model'] === 'reba'
            ? ['manual_handling', 'awkward_posture', 'reach_distance']
            : ['repetition', 'upper_limb_posture', 'contact_stress'];

        if ($baseline) {
            $factors[] = 'exposure_duration';
        }

        return $factors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bodyRegionsForTask(array $task, bool $baseline): array
    {
        if ($task['model'] === 'reba') {
            return [
                ['region' => 'Lower Back', 'side' => 'back', 'intensity' => $baseline ? 4 : 2],
                ['region' => 'Shoulders', 'side' => 'front', 'intensity' => $baseline ? 3 : 2],
                ['region' => 'Knees', 'side' => 'front', 'intensity' => $baseline ? 3 : 1],
            ];
        }

        return [
            ['region' => 'Wrists Hands', 'side' => 'front', 'intensity' => $baseline ? 4 : 2],
            ['region' => 'Shoulders', 'side' => 'front', 'intensity' => $baseline ? 3 : 2],
            ['region' => 'Neck', 'side' => 'front', 'intensity' => $baseline ? 3 : 1],
        ];
    }

    private function riskReduction(array $baseline, array $followUp): float
    {
        $before = (float) (($baseline['final_score']['raw_score'] ?? $baseline['initial_score']['raw_score']) ?: 0);
        $after = (float) (($followUp['final_score']['raw_score'] ?? $followUp['initial_score']['raw_score']) ?: 0);
        if ($before <= 0.0) {
            return 0.0;
        }

        return round((($before - $after) / $before) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreDiff(array $baseline, array $followUp): array
    {
        $before = (float) (($baseline['final_score']['raw_score'] ?? $baseline['initial_score']['raw_score']) ?: 0);
        $after = (float) (($followUp['final_score']['raw_score'] ?? $followUp['initial_score']['raw_score']) ?: 0);

        return [
            'rawScoreDelta' => round($after - $before, 2),
            'normalizedDelta' => round((float) (($followUp['final_score']['normalized_score'] ?? 0) - ($baseline['final_score']['normalized_score'] ?? 0)), 2),
            'direction' => $after < $before ? 'improved' : ($after > $before ? 'worsened' : 'unchanged'),
        ];
    }

    /**
     * @return list<string>
     */
    private function bodyRegionNames(array $baseline, array $followUp, bool $improved): array
    {
        $baselineRegions = $this->bodyRegionMap($baseline);
        $followUpRegions = $this->bodyRegionMap($followUp);
        $names = [];
        foreach ($baselineRegions as $region => $intensity) {
            $after = $followUpRegions[$region] ?? $intensity;
            if ($improved && $after < $intensity) {
                $names[] = $region;
            }
            if (!$improved && $after > $intensity) {
                $names[] = $region;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, int>
     */
    private function bodyRegionMap(array $assessment): array
    {
        $regions = [];
        foreach ((array) (($assessment['final_score'] ?? null) !== [] ? $assessment['task']['model'] : []) as $unused) {
            // no-op to preserve structure for static analysis
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT region, intensity FROM assessment_body_regions WHERE assessment_id = ? ORDER BY id ASC',
            [$assessment['id']],
        );
        foreach ($rows as $row) {
            $regions[(string) $row['region']] = (int) $row['intensity'];
        }

        return $regions;
    }

    private function feedbackBodyRegion(int $index): string
    {
        return ['Lower Back', 'Shoulders', 'Wrists Hands', 'Neck', 'Knees'][$index % 5];
    }

    private function statusNote(string $status): string
    {
        return match ($status) {
            'assigned' => 'Assigned to the frontline owner with due date and evidence expectations confirmed.',
            'in_progress' => 'Implementation started and workstation/process changes are underway.',
            'completed' => 'Implementation completed and ready for verification.',
            'verified' => 'Verification completed and improvement was confirmed through follow-up review.',
            'overdue' => 'Due date passed before completion; action remains on the open prevention backlog.',
            default => 'Status recorded by production seed helper.',
        };
    }

    private function countTasks(array $spec): int
    {
        $count = 0;
        foreach ((array) $spec['worksites'] as $worksite) {
            foreach ((array) $worksite['departments'] as $department) {
                $count += count((array) $department['tasks']);
            }
        }

        return $count;
    }

    private function uuid(string ...$parts): string
    {
        $hash = md5(implode(':', $parts));
        $timeHi = hexdec(substr($hash, 12, 4));
        $timeHi = ($timeHi & 0x0fff) | 0x4000;
        $clockSeq = hexdec(substr($hash, 16, 4));
        $clockSeq = ($clockSeq & 0x3fff) | 0x8000;

        return sprintf(
            '%08s-%04s-%04x-%04x-%012s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            $timeHi,
            $clockSeq,
            substr($hash, 20, 12),
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function timestamp(int $daysOffset = 0, int $minutesOffset = 0): string
    {
        $base = $this->baseDate ?? new DateTimeImmutable('2026-01-05 08:00:00');
        return $base->modify(sprintf('+%d days +%d minutes', $daysOffset, $minutesOffset))->format('Y-m-d H:i:s');
    }
}
