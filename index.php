<?php 
include 'config/db.php'; 

// HANDLE FORM REVIEW 
$reviewSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nama'], $_POST['ulasan'], $_POST['rating'])) {
    $nama = trim($_POST['nama']);
    $ulasan = trim($_POST['ulasan']);
    $rating = (int)$_POST['rating'];

    if ($nama !== '' && $ulasan !== '' && $rating > 0) {
        $stmt = $conn->prepare("INSERT INTO reviews (nama, ulasan, rating) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nama, $ulasan, $rating);
        if ($stmt->execute()) {
            $reviewSent = true;
        }
    }
}

// FILTER PENCARIAN - PERBAIKAN
$where = ["status = 'Available'"];
$params = [];
$types = "";

if (!empty($_GET['lokasi'])) {
    $where[] = "location LIKE ?";
    $params[] = "%" . $_GET['lokasi'] . "%";
    $types .= "s";
}
if (!empty($_GET['artis'])) {
    $where[] = "artist LIKE ?";
    $params[] = "%" . $_GET['artis'] . "%";
    $types .= "s";
}
if (!empty($_GET['tanggal'])) {
    $where[] = "date = ?";
    $params[] = $_GET['tanggal'];
    $types .= "s";
}

$sql = "SELECT * FROM concerts WHERE " . implode(" AND ", $where) . " ORDER BY date ASC";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Concertify - Cari Tiket Konser</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f8fafc;
      color: #333;
      scroll-behavior: smooth;
    }
    
    /* NAVBAR MODERN */
    .navbar {
      background: rgba(255, 255, 255, 0.95) !important;
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      padding: 1rem 0;
      transition: all 0.3s ease;
    }
    
    .navbar-brand {
  font-size: 1.5rem; /* Ubah jadi 1.5rem */
  font-weight: 700;
  color: #4e54c8 !important;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.ticket-icon {
  width: 28px; /* Ubah jadi 28px */
  height: 28px; /* Ubah jadi 28px */
  background: linear-gradient(135deg, #4e54c8, #8f94fb);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.2rem;
    
}
    
    .nav-link {
      font-weight: 500;
      color: #64748b !important;
      margin: 0 0.5rem;
      padding: 0.5rem 1rem !important;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .nav-link:hover {
      color: #4e54c8 !important;
      background: rgba(78, 84, 200, 0.1);
    }
    
    .dropdown-toggle {
      background: linear-gradient(135deg, #4e54c8, #8f94fb) !important;
      border: none !important;
      color: white !important;
      font-weight: 600;
      border-radius: 10px !important;
      padding: 0.6rem 1.2rem !important;
      box-shadow: 0 4px 12px rgba(78, 84, 200, 0.3);
    }
    
    .dropdown-toggle:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(78, 84, 200, 0.4);
    }
    
    /* HEADER MODERN */
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      position: relative;
      overflow: hidden;
      padding: 60px 20px;
      text-align: center;
      color: white;
    }
    
    .header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="0 0 0 100 1000 100"/></svg>');
      background-size: cover;
    }
    
    .header-content {
      position: relative;
      z-index: 2;
    }
    
    .header h1 {
  font-size: 2.5rem; /* Ubah jadi 2.5rem */
  font-weight: 800;
  margin-bottom: 1rem;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.header p {
  font-size: 1.1rem; /* Ubah jadi 1.1rem */
  opacity: 0.95;
  font-weight: 400;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}
    
    /* FILTER SECTION */
    .filter {
      background: white;
      padding: 20px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
      margin-top: -50px;
      margin-bottom: 60px;
      position: relative;
      z-index: 3;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .filter .form-control {
      border-radius: 12px;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .filter .form-control:focus {
      border-color: #4e54c8;
      box-shadow: 0 0 0 3px rgba(78, 84, 200, 0.1);
    }
    
    .filter .btn-primary {
      background: linear-gradient(135deg, #4e54c8, #8f94fb);
      border: none;
      border-radius: 12px;
      padding: 12px 20px;
      font-weight: 600;
      box-shadow: 0 4px 15px rgba(78, 84, 200, 0.3);
    }
    
/* CAROUSEL BASIC - GANTI SEMUA CSS CAROUSEL DENGAN INI */
.carousel-container {
  margin: 60px auto;
  max-width: 800px;
  width: 90%;
}

.carousel {
  border-radius: 15px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.carousel-inner img {
  width: 100%;
  height: 400px;
  object-fit: cover;
}

.carousel-caption-modern {
  position: absolute;
  bottom: 20px;
  left: 20px;
  right: 20px;
  background: rgba(0, 0, 0, 0.7);
  padding: 20px;
  border-radius: 10px;
  color: white;
}

.carousel-caption-modern h2 {
  margin: 0;
  font-size: 2rem;
  font-weight: bold;
}
    
    /* CONCERT CARDS */
    .concert-list {
      margin-top: 80px;
    }
    
    .concert-list .card {
      border: none;
      border-radius: 20px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      overflow: hidden;
      background: white;
    }
    
    .concert-list .card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }
    
    .object-fit-cover {
      object-fit: cover;
      height: 250px;
      border-radius: 15px;
    }
    
    .date-badge {
      background: linear-gradient(135deg, #4e54c8, #8f94fb);
      color: white;
      padding: 10px 18px;
      border-radius: 25px;
      font-size: 0.9rem;
      font-weight: 700;
      display: inline-block;
      margin-bottom: 15px;
      box-shadow: 0 4px 15px rgba(78, 84, 200, 0.3);
    }
    .concert-list .card h4 {
  font-size: 1.3rem; /* Tambahkan ini */
}

.concert-list .card p {
  font-size: 0.9rem; /* Tambahkan ini */
}
    /* TENTANG KAMI SECTION */
    #tentangKami {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      position: relative;
      overflow: hidden;
      margin: 100px 0;
      padding: 80px 0;
    }
    
    #tentangKami::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.2);
      z-index: 1;
    }
    
    #tentangKami .container {
      position: relative;
      z-index: 2;
    }
    
    .feature-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 25px;
      padding: 40px 30px;
      height: 100%;
      transition: all 0.3s ease;
    }
    
    .feature-card:hover {
      transform: translateY(-15px);
      background: rgba(255, 255, 255, 0.2);
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }
    
    .feature-icon {
      width: 90px;
      height: 90px;
      background: linear-gradient(135deg, #ff6b6b, #ffa726);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }
    
/* FROM CUSTOMER SECTION - PERBAIKAN */
#fromCustomer {
  margin: 80px 0;
  padding: 60px 0;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

/* Container utama dari customer harus full width */
#fromCustomer .container {
  max-width: 100%;
  padding-left: 15px;
  padding-right: 15px;
}

/* Content wrapper untuk membatasi lebar konten */
.customer-content {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

.review-form {
  background: white;
  padding: 40px;
  border-radius: 25px;
  margin-bottom: 60px;
  box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
  border: 1px solid rgba(0, 0, 0, 0.05);
  width: 100%;
  max-width: none;
}

/* Review list container */
.reviews-container {
  width: 100%;
}

.alert-img {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
  padding: 25px;
  border-radius: 20px;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
  width: 100%;
  max-width: none;
}

.alert-img img {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid #4e54c8;
  box-shadow: 0 4px 15px rgba(78, 84, 200, 0.2);
  flex-shrink: 0;
}

.alert-img .flex-grow-1 {
  flex: 1;
  min-width: 0;
}

/* Empty state styling */
.empty-reviews {
  background: white;
  border-radius: 20px;
  padding: 60px 40px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  #fromCustomer {
    margin: 60px 0;
    padding: 40px 0;
  }
  
  #fromCustomer .container {
    padding-left: 10px;
    padding-right: 10px;
  }
  
  .review-form {
    padding: 25px;
    border-radius: 20px;
  }
  
  .alert-img {
    padding: 20px;
    gap: 15px;
  }
  
  .alert-img img {
    width: 50px;
    height: 50px;
  }
}
    
    footer {
      background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
      color: white;
      padding: 60px 0 40px;
      margin-top: 0;
      position: relative;
      overflow: hidden;
    }
    
    footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.05)"><polygon points="0 0 1000 0 1000 100"/></svg>');
      background-size: cover;
    }
    
    footer .container {
      position: relative;
      z-index: 2;
    }
    
    footer a {
      color: #94a3b8;
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
    }
    
    footer a:hover {
      color: white;
      text-shadow: 0 2px 10px rgba(255, 255, 255, 0.3);
    }
    
    /* STAR RATING */
    .star-rating {
      font-size: 1.1rem;
      line-height: 1;
    }
    
    .star-rating i {
      margin-right: 3px;
    }
    
    /* RESPONSIVE IMPROVEMENTS */
    @media (max-width: 768px) {
      .header h1 {
        font-size: 2.5rem;
      }
      
      .carousel-caption-modern h2 {
        font-size: 2rem;
      }
      
      .filter {
        margin-top: -30px;
        padding: 20px;
      }
      
      .navbar-brand {
        font-size: 1.5rem;
      }
      
      .ticket-icon {
        width: 28px;
        height: 28px;
      }
  .carousel-container {
    width: 95%;
    margin: 60px auto;
  }
  
  .carousel-caption-modern {
    bottom: 20px;
    left: 20px;
    right: 20px;
    padding: 20px;
  }
  
  .carousel-caption-modern h2 {
    font-size: 1.5rem;
  }
}

.star-rating-input {
  position: relative;
}

.stars-container {
  display: flex;
  gap: 5px;
  margin-bottom: 5px;
}

.star-clickable {
  font-size: 1.5rem;
  color: #e2e8f0;
  cursor: pointer;
  transition: all 0.2s ease;
}

.star-clickable:hover {
  color: #fbbf24;
  transform: scale(1.1);
}

.star-clickable.active {
  color: #fbbf24;
}

.star-clickable.active:hover {
  color: #f59e0b;
}

.rating-text {
  font-size: 0.875rem;
  color: #6b7280;
}

.rating-selected {
  color: #059669 !important;
  font-weight: 600;
}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="#">
      <div class="ticket-icon">
        <i class="bi bi-ticket-perforated-fill"></i>
      </div>
      <strong>Concertify</strong>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNavDropdown">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item"><a class="nav-link" href="../konser/user/Shop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="#tentangKami">Tentang Kami</a></li>
        <li class="nav-item"><a class="nav-link" href="#fromCustomer">From Customer</a></li>
        <li class="nav-item dropdown ms-3">
          <a class="nav-link dropdown-toggle btn btn-outline-primary px-3 py-1" href="#" role="button" data-bs-toggle="dropdown"> Akun </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="../konser/auth/login.php">Login</a></li>
            <li><a class="dropdown-item" href="../konser/auth/register.php">Daftar</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../konser/user/profile.php">Profil Saya</a></li>
            <li><a class="dropdown-item" href="../konser/user/eticket.php">Daftar E-Tiket</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="header">
  <div class="header-content">
    <h1>Jelajahi & Dapatkan Tiket Konser Idola Anda</h1>
    <p>Ciptakan momen musik tak terlupakan bersama artis favorit</p>
  </div>
</div>

<div class="container">
  <form method="GET" class="filter row justify-content-center g-3">
    <div class="col-md-3">
      <input type="text" name="lokasi" class="form-control" placeholder="Cari Lokasi" value="<?= htmlspecialchars($_GET['lokasi'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <input type="text" name="artis" class="form-control" placeholder="Cari Artis" value="<?= htmlspecialchars($_GET['artis'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_GET['tanggal'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-12 mt-3">
      <a href="?" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
    </div>
  </form>
</div>

<!-- HASIL FILTER -->
<?php if (!empty($_GET['lokasi']) || !empty($_GET['artis']) || !empty($_GET['tanggal'])): ?>
<div class="container mb-4">
  <div class="alert alert-info">
    <h5>Hasil Pencarian:</h5>
    <?php
    $filters = [];
    if (!empty($_GET['lokasi'])) $filters[] = "Lokasi: " . htmlspecialchars($_GET['lokasi']);
    if (!empty($_GET['artis'])) $filters[] = "Artis: " . htmlspecialchars($_GET['artis']);
    if (!empty($_GET['tanggal'])) $filters[] = "Tanggal: " . date('d M Y', strtotime($_GET['tanggal']));
    echo implode(" | ", $filters);
    ?>
  </div>
  
  <div class="concert-list">
    <div class="row">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($concert = $result->fetch_assoc()): ?>
          <?php
          $stmt2 = $conn->prepare("SELECT category, price, stock FROM ticket_categories WHERE concert_id = ?");
          $stmt2->bind_param("i", $concert['id']);
          $stmt2->execute();
          $kategori = $stmt2->get_result();
          $imgPath = !empty($concert['image']) ? 'uploads/' . $concert['image'] : 'uploads/default-concert.jpg';
          ?>
          <div class="col-md-4 mb-4">
            <div class="card p-3 h-100">
              <img src="<?= htmlspecialchars($imgPath) ?>" class="card-img-top mb-3 object-fit-cover" alt="<?= htmlspecialchars($concert['artist']) ?>">
              <div class="date-badge">
                <?= date('d M Y', strtotime($concert['date'])) ?>
              </div>
              <h4><?= htmlspecialchars($concert['artist']) ?></h4>
              <p><strong>Lokasi:</strong> <?= htmlspecialchars($concert['location']) ?></p>
              <p><strong>Kategori Tiket:</strong></p>
              <ul>
                <?php while ($kt = $kategori->fetch_assoc()): ?>
                  <li><?= htmlspecialchars($kt['category']) ?> - Rp<?= number_format($kt['price'], 0, ',', '.') ?> (<?= (int)$kt['stock'] ?> tersedia)</li>
                <?php endwhile; ?>
              </ul>
              <a href="konser/detail.php?id=<?= (int)$concert['id'] ?>" class="btn btn-outline-primary mt-2">Lihat Detail</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-warning text-center">
            <h5>Tidak ada konser yang ditemukan</h5>
            <p>Coba ubah filter pencarian Anda atau <a href="?">lihat semua konser</a></p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container carousel-container">
  <div id="concertCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
      <div class="carousel-item active">
        <img src="../git/uploads/1747885658_svt.jpeg" class="d-block w-100" alt="SEVENTEEN">
        <div class="carousel-caption-modern">
          <h2>SEVENTEEN</h2>
        </div>
      </div>
      <div class="carousel-item active">
        <img src="../git/uploads/1747885816_day6.jpeg" class="d-block w-100" alt="Day6">
        <div class="carousel-caption-modern">
          <h2>Day6</h2>
        </div>
      </div>
      <div class="carousel-item active">
        <img src="../git/uploads/1748363263_10974de7097337f96344d25043ce0093.jpg" class="d-block w-100" alt="Straykids">
        <div class="carousel-caption-modern">
          <h2>Byeon Woo Seok</h2>
        </div>
      </div>
      <div class="carousel-item active">
        <img src="../git/uploads/1748179695_aespa.jpg" class="d-block w-100" alt="Aespa">
        <div class="carousel-caption-modern">
          <h2>Aespa</h2>
        </div>
      </div>
      <div class="carousel-item active">
        <img src="../git/uploads/1748362928_twice.jpg" class="d-block w-100" alt="Twice">
        <div class="carousel-caption-modern">
          <h2>Twice</h2>
        </div>
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#concertCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#concertCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon"></span>
    </button>
  </div>
</div>

<!-- KONSER TERDEKAT -->
<div class="container concert-list" id="daftarKonser">
  <h2 class="mb-5 text-center fw-bold">Konser Terdekat</h2>
  <div class="row">
    <?php
      $now = date('Y-m-d H:i:s'); // Gunakan waktu lengkap
      $oneMonthLater = date('Y-m-d', strtotime('+1 month'));

      $stmtTerdekat = $conn->prepare("
        SELECT * FROM concerts 
        WHERE status = 'Available' 
        AND date >= ? 
        AND date <= ? 
        ORDER BY date ASC 
        LIMIT 3
      ");
      $stmtTerdekat->bind_param("ss", $now, $oneMonthLater);
      $stmtTerdekat->execute();
      $terdekatResult = $stmtTerdekat->get_result();

      if ($terdekatResult->num_rows > 0):
        while ($concert = $terdekatResult->fetch_assoc()):
          $stmt2 = $conn->prepare("SELECT category, price, stock FROM ticket_categories WHERE concert_id = ?");
          $stmt2->bind_param("i", $concert['id']);
          $stmt2->execute();
          $kategori = $stmt2->get_result();
          $imgPath = !empty($concert['image']) ? 'uploads/' . $concert['image'] : 'uploads/default-concert.jpg';

          $concertDate = new DateTime($concert['date']);
          $currentDate = new DateTime(); // waktu sekarang
          $interval = $currentDate->diff($concertDate);
          $daysLeft = (int)$interval->format('%r%a'); // hasil bisa negatif

          if ($daysLeft < 0) continue; // Lewati konser yang sudah lewat
    ?>
      <div class="col-md-4 mb-4">
        <div class="card p-4 h-100">
          <img src="<?= htmlspecialchars($imgPath) ?>" class="card-img-top mb-3 object-fit-cover" alt="<?= htmlspecialchars($concert['artist']) ?>">

          <div class="date-badge">
            <?= date('d M Y', strtotime($concert['date'])) ?>
            <?php if ($daysLeft == 0): ?>
              <span class="badge bg-danger ms-2">HARI INI</span>
            <?php elseif ($daysLeft == 1): ?>
              <span class="badge bg-danger ms-2">BESOK</span>
            <?php elseif ($daysLeft <= 3): ?>
              <span class="badge bg-warning ms-2"><?= $daysLeft ?> hari lagi</span>
            <?php elseif ($daysLeft <= 7): ?>
              <span class="badge bg-info ms-2"><?= $daysLeft ?> hari lagi</span>
            <?php elseif ($daysLeft <= 14): ?>
              <span class="badge bg-secondary ms-2"><?= $daysLeft ?> hari lagi</span>
            <?php else: ?>
              <span class="badge bg-light text-dark ms-2"><?= $daysLeft ?> hari lagi</span>
            <?php endif; ?>
          </div>

          <h4><?= htmlspecialchars($concert['artist']) ?></h4>
          <p><strong>Lokasi:</strong> <?= htmlspecialchars($concert['location']) ?></p>
          <p><strong>Kategori Tiket:</strong></p>
          <ul>
            <?php while ($kt = $kategori->fetch_assoc()): ?>
              <li><?= htmlspecialchars($kt['category']) ?> - Rp<?= number_format($kt['price'], 0, ',', '.') ?> (<?= (int)$kt['stock'] ?> tersedia)</li>
            <?php endwhile; ?>
          </ul>
          <a href="konser/detail.php?id=<?= (int)$concert['id'] ?>" class="btn btn-outline-primary mt-2">Lihat Detail</a>
        </div>
      </div>
    <?php
        endwhile;
      else:
    ?>
      <div class="col-12">
        <div class="alert alert-info text-center">
          <h5>Belum ada konser dalam 1 bulan ke depan</h5>
          <p>Pantau terus untuk update konser terbaru!</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- SECTION TENTANG KAMI - DIPERBAIKI -->
<section id="tentangKami" class="py-5 text-white">
  <div class="container text-center">
    <h2 class="mb-4 fw-bold">TENTANG KAMI</h2>
    <p class="lead mx-auto mb-5" style="max-width: 900px; font-size: 1.2rem; line-height: 1.8;">
      Concertify adalah promotor konser terpercaya dan terkemuka di Indonesia yang menghadirkan pengalaman hiburan kelas dunia bagi seluruh penggemar musik. Dengan jaringan luas dan pengalaman bertahun-tahun, kami berkomitmen untuk menghadirkan konser-konser spektakuler yang tidak hanya menghibur, tetapi juga meninggalkan kesan mendalam dan kenangan tak terlupakan.
    </p>

    <div class="row mt-5 g-4">
      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon">
            <i class="bi bi-lightbulb-fill fs-1 text-white"></i>
          </div>
          <h5 class="fw-bold mb-3">Inovatif</h5>
          <p class="mb-0">Kami terus melahirkan ide-ide segar untuk menciptakan pertunjukan konser yang unik dan penuh kejutan bagi setiap penonton.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon">
            <i class="bi bi-star-fill fs-1 text-white"></i>
          </div>
          <h5 class="fw-bold mb-3">Berkualitas</h5>
          <p class="mb-0">Setiap konser dirancang dengan detail dan kualitas produksi terbaik demi pengalaman yang tak terlupakan.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="feature-card text-center">
          <div class="feature-icon">
            <i class="bi bi-people-fill fs-1 text-white"></i>
          </div>
          <h5 class="fw-bold mb-3">Profesional</h5>
          <p class="mb-0">Tim kami terdiri dari para profesional yang berpengalaman di bidang hiburan dan event organizer.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container-fluid" id="fromCustomer">
  <div class="customer-content">
    <div class="text-center mb-5">
      <h2 class="fw-bold">From Customer</h2>
      <p class="lead text-muted">Apa kata mereka tentang pengalaman konser bersama Concertify</p>
    </div>

    <?php if ($reviewSent): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i>
        Terima kasih! Ulasan Anda telah dikirim dan akan segera ditampilkan.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="review-form">
      <h4 class="mb-4"><i class="bi bi-chat-quote me-2"></i>Bagikan Pengalaman Anda</h4>
      <form action="" method="post">
        <div class="row">
          <!-- Nama -->
          <div class="col-md-6 mb-3">
            <label for="nama" class="form-label fw-semibold">Nama Lengkap</label>
            <input type="text" class="form-control form-control-lg" id="nama" name="nama" placeholder="Masukkan nama Anda" required>
          </div>

          <!-- Rating -->
          <div class="col-md-6 mb-3">
            <label for="rating" class="form-label fw-semibold">Rating</label>
            <div class="star-rating-input">
              <input type="hidden" id="rating" name="rating" value="" required>
              <div class="stars-container">
                <i class="bi bi-star star-clickable" data-rating="1"></i>
                <i class="bi bi-star star-clickable" data-rating="2"></i>
                <i class="bi bi-star star-clickable" data-rating="3"></i>
                <i class="bi bi-star star-clickable" data-rating="4"></i>
                <i class="bi bi-star star-clickable" data-rating="5"></i>
              </div>
              <small class="text-muted rating-text">Klik bintang untuk memberikan rating</small>
            </div>
          </div>

          <!-- Ulasan -->
          <div class="col-md-12 mb-3">
            <label for="ulasan" class="form-label fw-semibold">Ulasan</label>
            <textarea class="form-control" id="ulasan" name="ulasan" rows="4" placeholder="Ceritakan pengalaman Anda menghadiri konser..." required></textarea>
          </div>
        </div>

        <!-- Tombol -->
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="bi bi-send me-2"></i>Kirim Ulasan
        </button>
      </form>
    </div>

    <!-- Menampilkan Semua Ulasan -->
    <div class="reviews-container">
      <h4 class="mb-4"><i class="bi bi-star-fill text-warning me-2"></i>Ulasan Pelanggan</h4>
      <?php
        $ulasanStmt = $conn->query("SELECT nama, rating, ulasan, created_at FROM reviews ORDER BY created_at DESC LIMIT 10");
        if ($ulasanStmt->num_rows > 0):
          while ($review = $ulasanStmt->fetch_assoc()):
      ?>
        <div class="alert-img">
          <img src="https://ui-avatars.com/api/?name=<?= urlencode($review['nama']) ?>&background=random&color=fff&size=60" alt="Avatar">
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <strong class="text-primary"><?= htmlspecialchars($review['nama']) ?></strong>
                <div class="star-rating mt-1">
                  <?php
                  $rating = $review['rating'];
                  for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $rating 
                      ? '<i class="bi bi-star-fill text-warning"></i>' 
                      : '<i class="bi bi-star text-warning"></i>';
                  }
                  ?>
                </div>
              </div>
              <small class="text-muted">
                <i class="bi bi-clock me-1"></i>
                <?= date('d M Y, H:i', strtotime($review['created_at'])) ?>
              </small>
            </div>
            <p class="mb-0 text-secondary"><?= nl2br(htmlspecialchars($review['ulasan'])) ?></p>
          </div>
        </div>
      <?php
          endwhile;
        else:
      ?>
        <div class="empty-reviews">
          <i class="bi bi-chat-text display-1 text-muted mb-3"></i>
          <p class="text-muted fs-5">Belum ada ulasan dari pelanggan.</p>
          <p class="text-muted">Jadilah yang pertama memberikan ulasan!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- FOOTER -->
<footer>
  <div class="container text-center">
    <p>&copy; <?= date('Y') ?> Concertify. Semua Hak Dilindungi.</p>
    <p>
      <strong>Email:</strong> concertify@example.com <br>
      <a href="https://instagram.com/concertify" target="_blank" class="text-light me-2">Instagram</a> |
      <a href="https://twitter.com/concertify" target="_blank" class="text-light mx-2">Twitter</a> |
      <a href="https://facebook.com/concertify" target="_blank" class="text-light ms-2">Facebook</a>
    </p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const stars = document.querySelectorAll('.star-clickable');
  const ratingInput = document.getElementById('rating');
  const ratingText = document.querySelector('.rating-text');
  
  const ratingLabels = {
    1: 'Buruk',
    2: 'Kurang',
    3: 'Biasa', 
    4: 'Bagus',
    5: 'Luar Biasa'
  };

  stars.forEach(star => {
    // Hover effect
    star.addEventListener('mouseenter', function() {
      const rating = parseInt(this.getAttribute('data-rating'));
      highlightStars(rating);
    });

    // Click effect
    star.addEventListener('click', function() {
      const rating = parseInt(this.getAttribute('data-rating'));
      ratingInput.value = rating;
      setRating(rating);
      ratingText.textContent = `Rating: ${rating} - ${ratingLabels[rating]}`;
      ratingText.classList.add('rating-selected');
    });
  });

  // Reset on mouse leave
  document.querySelector('.stars-container').addEventListener('mouseleave', function() {
    const currentRating = parseInt(ratingInput.value) || 0;
    setRating(currentRating);
  });

  function highlightStars(rating) {
    stars.forEach((star, index) => {
      if (index < rating) {
        star.classList.remove('bi-star');
        star.classList.add('bi-star-fill', 'active');
      } else {
        star.classList.remove('bi-star-fill', 'active');
        star.classList.add('bi-star');
      }
    });
  }

  function setRating(rating) {
    stars.forEach((star, index) => {
      if (index < rating) {
        star.classList.remove('bi-star');
        star.classList.add('bi-star-fill', 'active');
      } else {
        star.classList.remove('bi-star-fill', 'active');
        star.classList.add('bi-star');
      }
    });
  }
});
</script>
</body>
</html>