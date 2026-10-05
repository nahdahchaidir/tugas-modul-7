<?php
session_start();

// gabungin classnya di sini dlu karna di mac namanya bentrok
class GuestBook {
    private $pdo;

    public function __construct($dbFile = 'guestbook.sqlite') {
        try {
            // pake sqlite aja biar gampang ga usah setting mysql
            $this->pdo = new PDO("sqlite:" . __DIR__ . '/' . $dbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initDb();
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }

    private function initDb() {
        // bikin tabel otomatis kl blm ada
        $sql = "CREATE TABLE IF NOT EXISTS buku_tamu (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            email TEXT NOT NULL,
            pesan TEXT NOT NULL,
            tanggal_kirim DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    public function addEntry($nama, $email, $pesan) {
        // prepared statement biar aman dr sql injection
        $sql = "INSERT INTO buku_tamu (nama, email, pesan) VALUES (:nama, :email, :pesan)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':nama', $nama);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':pesan', $pesan);
        return $stmt->execute();
    }

    public function getEntries() {
        // ambil data urut dr yg paling baru
        $sql = "SELECT id, nama, email, pesan, tanggal_kirim FROM buku_tamu ORDER BY tanggal_kirim DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// bikin csrf token kl blm ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$guestbook = new GuestBook();
$pesan_sukses = '';
$pesan_error = [];

// proses form pas di submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // cek token csrf
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $pesan_error[] = "Validasi CSRF gagal. Permintaan tidak sah.";
    } else {
        // bersihin spasi
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pesan = trim($_POST['pesan'] ?? '');

        // validasi inputan
        if (empty($nama)) {
            $pesan_error[] = "Nama tidak boleh kosong.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan_error[] = "Format email tidak valid.";
        }
        if (strlen($pesan) < 5) {
            $pesan_error[] = "Pesan minimal harus 5 karakter.";
        }

        // kl aman, simpen ke db
        if (empty($pesan_error)) {
            if ($guestbook->addEntry($nama, $email, $pesan)) {
                $pesan_sukses = "Pesan berhasil dikirim dan disimpan!";
                // reset isian
                $nama = $email = $pesan = '';
            } else {
                $pesan_error[] = "Terjadi kesalahan saat menyimpan pesan.";
            }
        }
    }
}

$daftar_pesan = $guestbook->getEntries();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Tamu Perpustakaan</title>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --accent: #111827;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-main); margin: 0; padding: 40px 20px; line-height: 1.6; }
        .container { max-width: 700px; margin: 0 auto; }
        
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 30px; text-align: center; letter-spacing: -0.5px; }
        h2 { font-size: 18px; font-weight: 600; margin-top: 50px; margin-bottom: 20px; }
        
        /* Form Styles */
        .form-wrapper { background: #fff; border: 1px solid var(--border-color); padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #374151; }
        input[type="text"], input[type="email"], textarea { 
            width: 100%; padding: 12px 14px; font-size: 14px; 
            border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; 
            outline: none; transition: all 0.2s; background-color: #f9fafb; font-family: inherit; 
        }
        input:focus, textarea:focus { border-color: var(--accent); background-color: #fff; box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.1); }
        button { 
            background-color: var(--accent); color: #fff; padding: 14px; border: none; border-radius: 8px; 
            cursor: pointer; font-size: 15px; font-weight: 500; width: 100%; transition: background-color 0.2s; 
            margin-top: 10px;
        }
        button:hover { background-color: #374151; }
        
        /* Alert Styles */
        .alert { padding: 14px 16px; margin-bottom: 24px; border-radius: 8px; font-size: 14px; border-left: 4px solid transparent; }
        .alert-success { background-color: #f0fdf4; color: #166534; border-color: #22c55e; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border-color: #ef4444; }
        .alert ul { margin: 0; padding-left: 20px; }
        
        /* Table Styles */
        .table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        th, td { padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        th { background-color: #f9fafb; font-weight: 600; color: var(--text-muted); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:last-child td { border-bottom: none; }
        td { color: #374151; line-height: 1.5; }
        
        hr { display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Buku Tamu Digital</h1>
    
    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($pesan_sukses, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($pesan_error as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-wrapper">
        <form action="guestbook.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="form-group">
            <label for="nama">Nama Lengkap:</label>
            <input type="text" id="nama" name="nama" value="<?php echo isset($nama) ? htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="email">Alamat Email:</label>
            <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="pesan">Pesan (min. 5 karakter):</label>
            <textarea id="pesan" name="pesan" rows="5" required><?php echo isset($pesan) ? htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        </div>
        
        <button type="submit">Kirim Pesan</button>
        </form>
    </div>

    <h2>Daftar Pesan Masuk</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th width="15%">Tanggal</th>
                    <th width="25%">Nama</th>
                    <th width="20%">Email</th>
                    <th width="40%">Pesan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($daftar_pesan) > 0): ?>
                    <?php foreach ($daftar_pesan as $baris): ?>
                        <tr>
                            <td style="color: #6b7280; font-size: 13px;"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($baris['tanggal_kirim'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($baris['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($baris['email'], ENT_QUOTES, 'UTF-8'); ?>" style="color: #4b5563; text-decoration: none;"><?php echo htmlspecialchars($baris['email'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo nl2br(htmlspecialchars($baris['pesan'], ENT_QUOTES, 'UTF-8')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #6b7280; padding: 30px;">Belum ada pesan yang masuk. Jadilah yang pertama!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
