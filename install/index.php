<?php
define('BB_INSTALLING', true);

// Prevent re-installation if lock file exists
if (file_exists(__DIR__ . '/../config/installed.lock')) {
    header('Location: ../public/admin/login.php');
    exit();
}

// Server Requirement Checks
$requirements = [
    'PHP Version (>= 8.1)' => [
        'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'sub' => 'Current PHP: ' . PHP_VERSION
    ],
    'PDO MySQL Extension' => [
        'passed' => extension_loaded('pdo_mysql'),
        'sub' => 'Required for MySQL database operations'
    ],
    'cURL Extension' => [
        'passed' => extension_loaded('curl'),
        'sub' => 'Required for HTTP requests & Social APIs'
    ],
    'ZipArchive Extension' => [
        'passed' => extension_loaded('zip'),
        'sub' => 'Required for Package Builder'
    ],
    'JSON Extension' => [
        'passed' => extension_loaded('json'),
        'sub' => 'Required for dynamic layout builder'
    ],
    'Config Directory Writable' => [
        'passed' => is_writable(__DIR__ . '/../config') || is_writable(__DIR__ . '/..'),
        'sub' => 'Allows saving system configuration'
    ]
];

$allPassed = !in_array(false, array_column($requirements, 'passed'), true);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOGBUSTER — Installer Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full text-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-2xl bg-slate-800/90 border border-slate-700/60 rounded-2xl shadow-2xl backdrop-blur-xl overflow-hidden">
        
        <!-- Header -->
        <div class="bg-slate-950/60 px-8 py-6 border-b border-slate-700/60 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-blue-600/10 text-blue-400 rounded-xl border border-blue-500/20">
                    <i data-lucide="layers" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-wide">BLOGBUSTER</h1>
                    <p class="text-xs text-slate-400">System Installation Wizard</p>
                </div>
            </div>
            <div class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                v1.0.0
            </div>
        </div>

        <!-- Wizard Progress Bar -->
        <div class="grid grid-cols-4 border-b border-slate-700/60 bg-slate-900/40 text-xs font-medium">
            <div id="tab-step-1" class="py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-blue-500 text-blue-400">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span>1. Welcome & Activation</span>
            </div>
            <div id="tab-step-2" class="py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-transparent text-slate-500">
                <i data-lucide="database" class="w-4 h-4"></i>
                <span>2. Database Setup</span>
            </div>
            <div id="tab-step-3" class="py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-transparent text-slate-500">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span>3. Admin Credentials</span>
            </div>
            <div id="tab-step-4" class="py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-transparent text-slate-500">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span>4. Complete</span>
            </div>
        </div>

        <!-- Form Body -->
        <form action="process.php" method="POST" id="installer-form" class="p-8 space-y-6">

            <!-- STAGE 1: Welcome, Requirements & Key Verification -->
            <div id="step-1" class="space-y-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-base font-semibold text-white">Stage 1: System Requirements & Activation</h2>
                    <span class="text-xs text-slate-400">Environment Verification</span>
                </div>

                <div class="space-y-2.5">
                    <?php foreach ($requirements as $label => $req): ?>
                        <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/40 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <?php if ($req['passed']): ?>
                                    <div class="p-1 bg-emerald-500/10 text-emerald-400 rounded-lg">
                                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="p-1 bg-rose-500/10 text-rose-400 rounded-lg">
                                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="text-sm font-medium text-slate-200"><?= $label; ?></div>
                                    <div class="text-xs text-slate-400"><?= $req['sub']; ?></div>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-md <?= $req['passed'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'; ?>">
                                <?= $req['passed'] ? 'Passed' : 'Failed'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pt-2">
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">System Product Key / Activation Token</label>
                    <input type="text" name="app_key" id="app_key" required placeholder="Enter valid key from manager.pmhserver.name.ng" class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    <p class="text-[11px] text-slate-400 mt-1">Obtain a valid activation key from manager.pmhserver.name.ng to proceed.</p>
                </div>

                <div class="pt-4 flex justify-end">
                    <?php if ($allPassed): ?>
                        <button type="button" onclick="goToStep(2)" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition flex items-center space-x-2">
                            <span>Continue to Database Setup</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    <?php else: ?>
                        <div class="w-full p-3 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-xs text-center font-medium">
                            Please resolve all failing server requirements to continue installation.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STAGE 2: Database Configuration -->
            <div id="step-2" class="space-y-4 hidden">
                <div class="mb-2">
                    <h2 class="text-base font-semibold text-white">Stage 2: Database Setup & Schema</h2>
                    <p class="text-xs text-slate-400">Enter your MySQL connection details.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Database Host</label>
                        <input type="text" name="db_host" value="127.0.0.1" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Database Port</label>
                        <input type="number" name="db_port" value="3306" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Database Name</label>
                        <input type="text" name="db_name" value="blogbuster_db" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Database User</label>
                        <input type="text" name="db_user" value="root" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Database Password</label>
                        <input type="password" name="db_pass" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <button type="button" onclick="goToStep(1)" class="px-5 py-2.5 bg-slate-700/50 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-xl transition flex items-center space-x-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" onclick="goToStep(3)" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition flex items-center space-x-2">
                        <span>Continue to Admin Setup</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- STAGE 3: Admin Account Creation -->
            <div id="step-3" class="space-y-4 hidden">
                <div class="mb-2">
                    <h2 class="text-base font-semibold text-white">Stage 3: Admin Login Details</h2>
                    <p class="text-xs text-slate-400">Configure super administrator credentials and security PIN.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Admin Username</label>
                        <input type="text" name="admin_user" value="admin" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Admin Email</label>
                        <input type="email" name="admin_email" value="admin@example.com" required class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Admin Password</label>
                        <input type="password" name="admin_pass" minlength="8" required value="Password123!" class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Permanent Security PIN (6 digits)</label>
                        <input type="password" name="security_pin" minlength="6" maxlength="6" required value="123456" class="w-full px-3.5 py-2.5 bg-slate-900/80 border border-slate-700/60 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <button type="button" onclick="goToStep(2)" class="px-5 py-2.5 bg-slate-700/50 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-xl transition flex items-center space-x-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="submit" id="btn-submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm rounded-xl transition flex items-center space-x-2">
                        <i data-lucide="rocket" class="w-4 h-4"></i>
                        <span>Execute Installation</span>
                    </button>
                </div>
            </div>

        </form>

    </div>

    <script>
        lucide.createIcons();

        function goToStep(step) {
            document.getElementById('step-1').classList.add('hidden');
            document.getElementById('step-2').classList.add('hidden');
            document.getElementById('step-3').classList.add('hidden');

            document.getElementById('step-' + step).classList.remove('hidden');

            for (let i = 1; i <= 4; i++) {
                const tab = document.getElementById('tab-step-' + i);
                if (!tab) continue;
                if (i === step) {
                    tab.className = "py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-blue-500 text-blue-400";
                } else if (i < step) {
                    tab.className = "py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-emerald-500 text-emerald-400";
                } else {
                    tab.className = "py-3 px-2 flex items-center justify-center space-x-1.5 border-b-2 border-transparent text-slate-500";
                }
            }
        }
    </script>
</body>
</html>
