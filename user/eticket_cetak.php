<?php
include '../config/db.php';

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    die("ID tiket tidak ditemukan.");
}

// Ambil data order
$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.concert_id, u.name AS buyer, u.email, c.artist, c.location, c.date, t.category, o.quantity, o.total_price, o.created_at
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN concerts c ON o.concert_id = c.id
    JOIN ticket_categories t ON o.category_id = t.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die("Tiket tidak ditemukan.");
}

// Buat QR Code unik
$raw_data = $order['id'] . '|' . $order['user_id'] . '|' . $order['concert_id'] . '|' . $order['created_at'];
$qr_data = hash('sha256', $raw_data); // bisa diganti dengan data asli jika ingin dibaca langsung
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Tiket Konser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --card-shadow: 0 15px 35px rgba(0,0,0,0.1);
            --border-radius: 20px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            padding: 2rem;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            z-index: -1;
        }

        .ticket-container {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
        }

        .ticket-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .ticket-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .ticket-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .ticket-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .ticket-body {
            padding: 2rem;
        }

        .ticket-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem;
            border-radius: 15px;
            border-left: 4px solid;
            border-image: var(--primary-gradient) 1;
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        .artist-name {
            font-size: 1.2rem;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .price-value {
            font-size: 1.1rem;
            color: #2563eb;
            font-weight: 700;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-gradient), transparent);
            margin: 2rem 0;
            border-radius: 1px;
        }

        .qr-section {
            text-align: center;
            margin: 2rem 0;
        }

        .qr-container {
            display: inline-block;
            padding: 1.5rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 3px solid;
            border-image: var(--success-gradient) 1;
        }

        .qr-container img {
            border-radius: 10px;
        }

        .notice-section {
            background: var(--success-gradient);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin: 2rem 0;
        }

        .notice-text {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .print-section {
            text-align: center;
            padding: 1rem 0;
        }

        .print-btn {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0.5rem;
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .download-btn {
            background: var(--secondary-gradient);
            color: white;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0.5rem;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
        }

        .ticket-id {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            body::before {
                display: none;
            }

            .ticket-container {
                box-shadow: none;
                border: 2px solid #e2e8f0;
                background: white;
            }

            .print-section {
                display: none;
            }

            .ticket-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .info-card {
                background: #f8f9fa;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .notice-section {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .ticket-header {
                padding: 1.5rem;
            }

            .ticket-title {
                font-size: 1.5rem;
            }

            .ticket-subtitle {
                font-size: 1rem;
            }

            .ticket-body {
                padding: 1.5rem;
            }

            .ticket-info {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .info-card {
                padding: 1rem;
            }

            .qr-container {
                padding: 1rem;
            }

            .print-btn, .download-btn {
                margin: 0.25rem;
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
    <!-- HTML2Canvas Library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- jsPDF Library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<div class="ticket-container" id="ticketContainer">
    <div class="ticket-header">
        <div class="ticket-id">ID: #<?= $order['id'] ?></div>
        <h1 class="ticket-title">
            <i class="fas fa-ticket-alt"></i>
            E-TIKET KONSER
        </h1>
        <p class="ticket-subtitle">Tiket Digital Resmi</p>
    </div>

    <div class="ticket-body">
        <div class="ticket-info">
            <div class="info-card">
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Nama Pembeli</div>
                        <div class="info-value"><?= htmlspecialchars($order['buyer']) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($order['email']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Artis</div>
                        <div class="info-value artist-name"><?= htmlspecialchars($order['artist']) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Lokasi</div>
                        <div class="info-value"><?= htmlspecialchars($order['location']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Tanggal Konser</div>
                        <div class="info-value"><?= date('d M Y', strtotime($order['date'])) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Kategori</div>
                        <div class="info-value"><?= htmlspecialchars($order['category']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-sort-numeric-up"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Jumlah Tiket</div>
                        <div class="info-value"><?= $order['quantity'] ?> tiket</div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Total Harga</div>
                        <div class="info-value price-value">Rp<?= number_format($order['total_price'], 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="qr-section">
            <div class="qr-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($qr_data) ?>&amp;size=200x200" alt="QR Code Tiket">
            </div>
        </div>

        <div class="notice-section">
            <div class="notice-text">
                <i class="fas fa-info-circle"></i>
                Tunjukkan e-tiket ini saat masuk venue konser
            </div>
        </div>

        <div style="text-align: center; font-size: 0.85rem; color: #64748b; margin-top: 1rem;">
            <i class="fas fa-clock"></i> Dibuat pada: <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
        </div>
    </div>

    <div class="print-section">
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i>
            Cetak / Simpan PDF
        </button>
        <button class="download-btn" onclick="downloadTicket()">
            <i class="fas fa-download"></i>
            Download PDF
        </button>
    </div>
</div>

<script>
function downloadTicket() {
    // Hide the button section temporarily for clean capture
    const printSection = document.querySelector('.print-section');
    const originalDisplay = printSection.style.display;
    printSection.style.display = 'none';
    
    // Get the ticket container
    const element = document.getElementById('ticketContainer');
    
    // Configure html2canvas options
    const options = {
        scale: 2, // Higher quality
        useCORS: true,
        allowTaint: true,
        backgroundColor: null,
        width: element.offsetWidth,
        height: element.offsetHeight,
        scrollX: 0,
        scrollY: 0,
        windowWidth: window.innerWidth,
        windowHeight: window.innerHeight
    };
    
    // Generate canvas from HTML
    html2canvas(element, options).then(canvas => {
        // Restore the button section
        printSection.style.display = originalDisplay;
        
        // Create jsPDF instance
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        
        // Calculate dimensions to fit A4
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        
        let position = 0;
        
        // Add image to PDF
        const imgData = canvas.toDataURL('image/png');
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // Add new pages if content is longer than one page
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        // Generate filename
        const artistName = '<?= addslashes($order['artist']) ?>';
        const ticketId = '<?= $order['id'] ?>';
        const filename = `E-Tiket-${artistName.replace(/[^a-zA-Z0-9]/g, '')}-${ticketId}.pdf`;
        
        // Download the PDF
        pdf.save(filename);
        
    }).catch(error => {
        // Restore the button section on error
        printSection.style.display = originalDisplay;
        console.error('Error generating PDF:', error);
        alert('Terjadi kesalahan saat membuat PDF. Silakan coba lagi.');
    });
}

// Alternative download function using HTML content as fallback
function downloadTicketHTML() {
    // Create a new window with the ticket content
    const printWindow = window.open('', '_blank');
    const ticketHTML = document.getElementById('ticketContainer').outerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>E-Tiket Konser</title>
            <style>
                ${document.querySelector('style').innerHTML}
                .print-section { display: none; }
                body { background: white; padding: 20px; }
                body::before { display: none; }
                .ticket-container { 
                    box-shadow: none; 
                    border: 2px solid #e2e8f0; 
                    background: white; 
                }
            </style>
        </head>
        <body>
            ${ticketHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Trigger download
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}
</script>

</body>
</html>