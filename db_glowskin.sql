-- ========================================================
-- INFEKSTUR AWAL: MEMBUAT DAN MENGGUNAKAN DATABASE
-- ========================================================
CREATE DATABASE IF NOT EXISTS glowskin_db;
USE glowskin_db;

-- ========================================================
-- PILAR 1: TABEL REFERENSI (Wajib Paling Awal)
-- ========================================================

CREATE TABLE ref_jenis_kelamin (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(20) NOT NULL
);
INSERT INTO ref_jenis_kelamin (nama) VALUES ('Laki-laki'), ('Perempuan');

CREATE TABLE ref_golongan_darah (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(5) NOT NULL
);
INSERT INTO ref_golongan_darah (nama) VALUES ('A'), ('B'), ('AB'), ('O');

CREATE TABLE ref_status_pernikahan (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(20) NOT NULL
);
INSERT INTO ref_status_pernikahan (nama) VALUES ('Belum Menikah'), ('Menikah'), ('Janda/Duda');

CREATE TABLE ref_spesialisasi (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(50) NOT NULL
);
INSERT INTO ref_spesialisasi (nama) VALUES ('Dermatologi'), ('Kecantikan Kulit'), ('Estetika Medis'), ('Umum');

CREATE TABLE ref_jenis_layanan (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(50) NOT NULL
);
INSERT INTO ref_jenis_layanan (nama) VALUES ('Konsultasi'), ('Treatment Wajah'), ('Treatment Tubuh'), ('Perawatan Laser');

CREATE TABLE ref_metode_pembayaran (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(30) NOT NULL
);
INSERT INTO ref_metode_pembayaran (nama) VALUES ('Tunai'), ('Kartu Debit'), ('Kartu Kredit'), ('QRIS'), ('Transfer Bank');

CREATE TABLE ref_status_kunjungan (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(30) NOT NULL,
    warna   VARCHAR(10)
);
INSERT INTO ref_status_kunjungan (nama, warna) VALUES ('Menunggu', 'yellow'), ('Sedang Diperiksa', 'blue'), ('Selesai', 'green'), ('Batal', 'red');

CREATE TABLE ref_hari (
    id      TINYINT AUTO_INCREMENT PRIMARY KEY,
    nama    VARCHAR(10) NOT NULL
);
INSERT INTO ref_hari (nama) VALUES ('Senin'), ('Selasa'), ('Rabu'), ('Kamis'), ('Jumat'), ('Sabtu'), ('Minggu');

CREATE TABLE kategori_obat (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori   VARCHAR(50) NOT NULL UNIQUE,
    deskripsi       TEXT
);

-- ========================================================
-- PILAR 2: TABEL MASTER (Butuh Referensi)
-- ========================================================

CREATE TABLE pasien (
    id_pasien       INT AUTO_INCREMENT PRIMARY KEY,
    kode_pasien     VARCHAR(15) UNIQUE NOT NULL,
    nama_lengkap    VARCHAR(100) NOT NULL,
    nik             VARCHAR(16) UNIQUE,
    tanggal_lahir   DATE NOT NULL,
    id_jenis_kelamin TINYINT NOT NULL,
    id_golongan_darah TINYINT,
    id_status_pernikahan TINYINT,
    alamat          TEXT,
    no_telepon      VARCHAR(15),
    email           VARCHAR(100),
    alergi          TEXT,
    kategori        ENUM('reguler', 'member', 'vip') NOT NULL DEFAULT 'reguler',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_jenis_kelamin) REFERENCES ref_jenis_kelamin(id),
    FOREIGN KEY (id_golongan_darah) REFERENCES ref_golongan_darah(id),
    FOREIGN KEY (id_status_pernikahan) REFERENCES ref_status_pernikahan(id)
);

CREATE TABLE dokter (
    id_dokter       INT AUTO_INCREMENT PRIMARY KEY,
    kode_dokter     VARCHAR(15) UNIQUE NOT NULL,
    nama_lengkap    VARCHAR(100) NOT NULL,
    id_spesialisasi INT NOT NULL,
    no_str          VARCHAR(30) UNIQUE,
    no_telepon      VARCHAR(15),
    email           VARCHAR(100),
    foto_url        VARCHAR(255),
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_spesialisasi) REFERENCES ref_spesialisasi(id)
);

CREATE TABLE staf (
    id_staf         INT AUTO_INCREMENT PRIMARY KEY,
    kode_staf       VARCHAR(15) UNIQUE NOT NULL,
    nama_lengkap    VARCHAR(100) NOT NULL,
    jabatan         VARCHAR(50) NOT NULL,
    no_telepon      VARCHAR(15),
    email           VARCHAR(100),
    username        VARCHAR(50) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('super_admin', 'admin', 'resepsionis', 'apoteker') NOT NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    last_login      TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE layanan (
    id_layanan      INT AUTO_INCREMENT PRIMARY KEY,
    kode_layanan    VARCHAR(15) UNIQUE NOT NULL,
    nama_layanan    VARCHAR(100) NOT NULL,
    id_jenis_layanan INT NOT NULL,
    deskripsi       TEXT,
    harga           DECIMAL(12,2) NOT NULL,
    durasi_menit    INT DEFAULT 30,
    is_active       BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_jenis_layanan) REFERENCES ref_jenis_layanan(id)
);

CREATE TABLE obat (
    id_obat         INT AUTO_INCREMENT PRIMARY KEY,
    kode_obat       VARCHAR(15) UNIQUE NOT NULL,
    nama_obat       VARCHAR(100) NOT NULL,
    id_kategori     INT NOT NULL,
    satuan          VARCHAR(20) NOT NULL,
    harga_jual      DECIMAL(12,2) NOT NULL,
    harga_beli      DECIMAL(12,2) NOT NULL,
    stok            INT NOT NULL DEFAULT 0,
    stok_minimum    INT NOT NULL DEFAULT 10,
    tanggal_kadaluarsa DATE,
    gambar_url      VARCHAR(255) DEFAULT NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori_obat(id)
);

CREATE TABLE supplier (
    id_supplier     INT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier   VARCHAR(100) NOT NULL,
    alamat          TEXT,
    no_telepon      VARCHAR(15),
    email           VARCHAR(100),
    kontak_person   VARCHAR(100),
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================================
-- PILAR 3: TABEL TRANSAKSI & OPERASIONAL[cite: 1]
-- ========================================================

CREATE TABLE kunjungan (
    id_kunjungan    INT AUTO_INCREMENT PRIMARY KEY,
    kode_kunjungan  VARCHAR(20) UNIQUE NOT NULL,
    id_pasien       INT NOT NULL,
    id_dokter       INT NOT NULL,
    id_staf         INT NOT NULL,
    id_status       TINYINT NOT NULL DEFAULT 1,
    tanggal_kunjungan DATE NOT NULL,
    waktu_daftar    TIME NOT NULL,
    waktu_mulai     TIME,
    waktu_selesai   TIME,
    keluhan_utama   TEXT NOT NULL,
    no_antrian      INT NOT NULL,
    catatan         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pasien) REFERENCES pasien(id_pasien),
    FOREIGN KEY (id_dokter) REFERENCES dokter(id_dokter),
    FOREIGN KEY (id_staf) REFERENCES staf(id_staf),
    FOREIGN KEY (id_status) REFERENCES ref_status_kunjungan(id)
);

CREATE TABLE rekam_medis (
    id_rekam_medis  INT AUTO_INCREMENT PRIMARY KEY,
    id_kunjungan    INT NOT NULL UNIQUE,
    anamnesis       TEXT NOT NULL,
    pemeriksaan_fisik TEXT,
    tekanan_darah   VARCHAR(10),
    suhu_tubuh      DECIMAL(4,1),
    berat_badan     DECIMAL(5,1),
    tinggi_badan    DECIMAL(5,1),
    diagnosa        TEXT NOT NULL,
    tindakan        TEXT,
    catatan_dokter  TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan)
);

CREATE TABLE detail_layanan (
    id_detail       INT AUTO_INCREMENT PRIMARY KEY,
    id_kunjungan    INT NOT NULL,
    id_layanan      INT NOT NULL,
    jumlah          INT NOT NULL DEFAULT 1,
    harga_satuan    DECIMAL(12,2) NOT NULL,
    diskon_persen   DECIMAL(5,2) DEFAULT 0,
    subtotal        DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan),
    FOREIGN KEY (id_layanan) REFERENCES layanan(id_layanan)
);

CREATE TABLE resep_obat (
    id_resep        INT AUTO_INCREMENT PRIMARY KEY,
    id_kunjungan    INT NOT NULL,
    id_obat         INT NOT NULL,
    jumlah          INT NOT NULL,
    aturan_pakai    VARCHAR(100),
    harga_satuan    DECIMAL(12,2) NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL,
    catatan         TEXT,
    FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan),
    FOREIGN KEY (id_obat) REFERENCES obat(id_obat)
);

CREATE TABLE pembayaran (
    id_pembayaran   INT AUTO_INCREMENT PRIMARY KEY,
    kode_pembayaran VARCHAR(20) UNIQUE NOT NULL,
    id_kunjungan    INT NOT NULL UNIQUE,
    total_layanan   DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_obat      DECIMAL(12,2) NOT NULL DEFAULT 0,
    diskon_total    DECIMAL(12,2) DEFAULT 0,
    pajak           DECIMAL(12,2) DEFAULT 0,
    grand_total     DECIMAL(12,2) NOT NULL,
    id_metode       TINYINT NOT NULL,
    status_bayar    ENUM('lunas', 'belum_lunas', 'batal') DEFAULT 'belum_lunas',
    tanggal_bayar   TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kunjungan) REFERENCES kunjungan(id_kunjungan),
    FOREIGN KEY (id_metode) REFERENCES ref_metode_pembayaran(id)
);

CREATE TABLE stok_masuk (
    id_stok_masuk   INT AUTO_INCREMENT PRIMARY KEY,
    id_obat         INT NOT NULL,
    id_supplier     INT NOT NULL,
    jumlah          INT NOT NULL,
    harga_beli      DECIMAL(12,2) NOT NULL,
    tanggal_masuk   DATE NOT NULL,
    no_faktur       VARCHAR(30),
    catatan         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_obat) REFERENCES obat(id_obat),
    FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier)
);

CREATE TABLE jadwal_dokter (
    id_jadwal       INT AUTO_INCREMENT PRIMARY KEY,
    id_dokter       INT NOT NULL,
    id_hari         TINYINT NOT NULL,
    jam_mulai       TIME NOT NULL,
    jam_selesai     TIME NOT NULL,
    kuota_pasien    INT DEFAULT 20,
    is_active       BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_dokter) REFERENCES dokter(id_dokter),
    FOREIGN KEY (id_hari) REFERENCES ref_hari(id)
);

CREATE TABLE audit_log (
    id_log          INT AUTO_INCREMENT PRIMARY KEY,
    nama_tabel      VARCHAR(50) NOT NULL,
    id_record       INT NOT NULL,
    aksi            ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    data_lama       JSON,
    data_baru       JSON,
    id_staf         INT,
    keterangan      TEXT,
    ip_address      VARCHAR(45),
    sumber          VARCHAR(50),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_staf) REFERENCES staf(id_staf)
);

-- ========================================================
-- PILAR 4: PROGRAMMABLE OBJECTS (DELIMITER BERMAIN)[cite: 1]
-- ========================================================

-- ========================================================
-- [SS-LAPORAN: BAB V - TRIGGER 1: AUDIT LOG PASIEN]
-- ========================================================
DELIMITER //
CREATE TRIGGER trg_pasien_after_update
AFTER UPDATE ON pasien
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (nama_tabel, id_record, aksi, data_lama, data_baru, keterangan, ip_address, sumber)
    VALUES (
        'pasien',
        OLD.id_pasien,
        'UPDATE',
        JSON_OBJECT(
            'nama_lengkap', OLD.nama_lengkap,
            'no_telepon', OLD.no_telepon,
            'alamat', OLD.alamat,
            'email', OLD.email
        ),
        JSON_OBJECT(
            'nama_lengkap', NEW.nama_lengkap,
            'no_telepon', NEW.no_telepon,
            'alamat', NEW.alamat,
            'email', NEW.email
        ),
        CONCAT('Data pasien ', OLD.kode_pasien, ' diperbarui'),
        COALESCE(@current_ip, '127.0.0.1'),
        COALESCE(@current_source, 'Sistem')
    );
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - TRIGGER 2: VALIDASI POTONG STOK]
-- ========================================================
DELIMITER //
CREATE TRIGGER trg_resep_after_insert
AFTER INSERT ON resep_obat
FOR EACH ROW
BEGIN
    DECLARE stok_sekarang INT;
    SELECT stok INTO stok_sekarang FROM obat WHERE id_obat = NEW.id_obat;
    IF stok_sekarang < NEW.jumlah THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ERROR: Stok obat tidak mencukupi!';
    END IF;
    UPDATE obat SET stok = stok - NEW.jumlah WHERE id_obat = NEW.id_obat;
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - TRIGGER 3: UPDATE STATUS KUNJUNGAN & AUDIT LOG]
-- ========================================================
DELIMITER //
CREATE TRIGGER trg_rekam_medis_after_insert
AFTER INSERT ON rekam_medis
FOR EACH ROW
BEGIN
    DECLARE v_kode_pasien VARCHAR(15);
    DECLARE v_nama_pasien VARCHAR(100);

    -- Update status kunjungan menjadi selesai (id_status=3)
    UPDATE kunjungan 
    SET id_status = 3, 
        waktu_selesai = CURRENT_TIME()
    WHERE id_kunjungan = NEW.id_kunjungan;

    -- Ambil data kode_pasien dan nama_lengkap
    SELECT p.kode_pasien, p.nama_lengkap INTO v_kode_pasien, v_nama_pasien
    FROM kunjungan k
    JOIN pasien p ON k.id_pasien = p.id_pasien
    WHERE k.id_kunjungan = NEW.id_kunjungan;

    -- Tulis ke audit log sebagai penambahan data rekam medis sensitif
    INSERT INTO audit_log (nama_tabel, id_record, aksi, id_staf, data_baru, keterangan, ip_address, sumber)
    VALUES (
        'rekam_medis',
        NEW.id_rekam_medis,
        'INSERT',
        NULL,
        JSON_OBJECT(
            'id_kunjungan', NEW.id_kunjungan,
            'anamnesis', NEW.anamnesis,
            'diagnosa', NEW.diagnosa
        ),
        CONCAT('Dokter menginput rekam medis baru untuk pasien ', v_nama_pasien, ' (', v_kode_pasien, ')'),
        COALESCE(@current_ip, '127.0.0.1'),
        COALESCE(@current_source, 'Dokter Dashboard')
    );
END //
DELIMITER ;




-- ========================================================
-- [SS-LAPORAN: BAB V - UDF 1: HITUNG BIAYA KUNJUNGAN]
-- ========================================================
DELIMITER //
CREATE FUNCTION fn_hitung_total_biaya(p_id_kunjungan INT)
RETURNS DECIMAL(12,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_layanan DECIMAL(12,2) DEFAULT 0;
    DECLARE total_obat DECIMAL(12,2) DEFAULT 0;
    
    SELECT COALESCE(SUM(subtotal), 0) INTO total_layanan FROM detail_layanan WHERE id_kunjungan = p_id_kunjungan;
    SELECT COALESCE(SUM(subtotal), 0) INTO total_obat FROM resep_obat WHERE id_kunjungan = p_id_kunjungan;
    
    RETURN total_layanan + total_obat;
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - UDF 2: HITUNG UMUR PASIEN]
-- ========================================================
DELIMITER //
CREATE FUNCTION fn_hitung_umur(p_tanggal_lahir DATE)
RETURNS INT
DETERMINISTIC
NO SQL
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_tanggal_lahir, CURDATE());
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - STORED PROCEDURE 1: TAMBAH ANTREAN]
-- ========================================================
DELIMITER //
CREATE PROCEDURE sp_tambah_kunjungan(
    IN p_id_pasien INT,
    IN p_id_dokter INT,
    IN p_id_staf INT,
    IN p_keluhan TEXT,
    OUT p_kode_kunjungan VARCHAR(20),
    OUT p_no_antrian INT
)
BEGIN
    DECLARE v_tanggal DATE;
    DECLARE v_kode VARCHAR(20);
    DECLARE v_antrian INT;
    DECLARE v_global_seq INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_kode_kunjungan = NULL;
        SET p_no_antrian = 0;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gagal mendaftarkan kunjungan!';
    END;
    
    SET v_tanggal = CURDATE();
    START TRANSACTION;
    
    -- Hitung no_antrian harian per dokter
    SELECT COALESCE(MAX(no_antrian), 0) + 1 INTO v_antrian
    FROM kunjungan WHERE id_dokter = p_id_dokter AND tanggal_kunjungan = v_tanggal;
    
    -- Hitung urutan global kunjungan hari ini untuk kode_kunjungan unik
    SELECT COALESCE(COUNT(*), 0) + 1 INTO v_global_seq
    FROM kunjungan WHERE tanggal_kunjungan = v_tanggal;
    
    SET v_kode = CONCAT('KNJ-', DATE_FORMAT(v_tanggal, '%Y%m%d'), '-', LPAD(v_global_seq, 3, '0'));
    
    INSERT INTO kunjungan (kode_kunjungan, id_pasien, id_dokter, id_staf, tanggal_kunjungan, waktu_daftar, keluhan_utama, no_antrian)
    VALUES (v_kode, p_id_pasien, p_id_dokter, p_id_staf, v_tanggal, CURRENT_TIME(), p_keluhan, v_antrian);
    
    COMMIT;
    SET p_kode_kunjungan = v_kode;
    SET p_no_antrian = v_antrian;
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - STORED PROCEDURE 2: TERIMA STOK]
-- ========================================================
DELIMITER //
CREATE PROCEDURE sp_terima_stok(
    IN p_id_obat INT,
    IN p_id_supplier INT,
    IN p_jumlah INT,
    IN p_harga_beli DECIMAL(12,2),
    IN p_no_faktur VARCHAR(30)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gagal menerima stok!';
    END;
    
    START TRANSACTION;
    INSERT INTO stok_masuk (id_obat, id_supplier, jumlah, harga_beli, tanggal_masuk, no_faktur)
    VALUES (p_id_obat, p_id_supplier, p_jumlah, p_harga_beli, CURDATE(), p_no_faktur);
    
    UPDATE obat SET stok = stok + p_jumlah, harga_beli = p_harga_beli WHERE id_obat = p_id_obat;
    COMMIT;
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - STORED PROCEDURE 3: LAPORAN PENDAPATAN]
-- ========================================================
DELIMITER //
CREATE PROCEDURE sp_laporan_pendapatan(
    IN p_bulan INT,
    IN p_tahun INT
)
BEGIN
    SELECT 
        CONCAT(MONTHNAME(STR_TO_DATE(p_bulan, '%m')), ' ', p_tahun) AS periode,
        COUNT(*) AS total_transaksi,
        SUM(total_layanan) AS pendapatan_layanan,
        SUM(total_obat) AS pendapatan_obat,
        SUM(grand_total) AS total_pendapatan,
        AVG(grand_total) AS rata_rata_transaksi,
        MAX(grand_total) AS transaksi_tertinggi,
        MIN(grand_total) AS transaksi_terendah
    FROM pembayaran WHERE status_bayar = 'lunas' AND MONTH(tanggal_bayar) = p_bulan AND YEAR(tanggal_bayar) = p_tahun;
    
    SELECT DATE(tanggal_bayar) AS tanggal, COUNT(*) AS jumlah_transaksi, SUM(grand_total) AS total_harian
    FROM pembayaran WHERE status_bayar = 'lunas' AND MONTH(tanggal_bayar) = p_bulan AND YEAR(tanggal_bayar) = p_tahun
    GROUP BY DATE(tanggal_bayar) ORDER BY tanggal;
END //
DELIMITER ;

-- ========================================================
-- [SS-LAPORAN: BAB V - CURSOR 1: BATCH STOK MINIMUM]
-- ========================================================
DELIMITER //
CREATE PROCEDURE sp_cek_stok_minimum()
BEGIN
    DECLARE v_id_obat INT;
    DECLARE v_nama VARCHAR(100);
    DECLARE v_stok INT;
    DECLARE v_minimum INT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_count INT DEFAULT 0;
    
    DECLARE cur_obat CURSOR FOR
        SELECT id_obat, nama_obat, stok, stok_minimum FROM obat WHERE stok <= stok_minimum AND is_active = TRUE;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    OPEN cur_obat;
    loop_obat: LOOP
        FETCH cur_obat INTO v_id_obat, v_nama, v_stok, v_minimum;
        IF v_done THEN
            LEAVE loop_obat;
        END IF;
        
        INSERT INTO audit_log (nama_tabel, id_record, aksi, keterangan, ip_address, sumber)
        VALUES ('obat', v_id_obat, 'UPDATE', CONCAT('⚠️ ALERT STOK: ', v_nama, ' — Stok sisa: ', v_stok, ', Minimum: ', v_minimum, '. Segera restock!'), '127.0.0.1', 'Sistem');
        SET v_count = v_count + 1;
    END LOOP;
    CLOSE cur_obat;
    SELECT CONCAT(v_count, ' obat memiliki stok di bawah minimum') AS hasil;
END //
DELIMITER ;


USE glowskin_db;

-- ========================================================
-- 1. DATA DUMMY: KATEGORI OBAT & OPERASIONAL STAF
-- ========================================================
INSERT INTO kategori_obat (nama_kategori, deskripsi) VALUES
('Skincare Serum', 'Produk serum wajah konsentrasi tinggi'),
('Cream & Moisturizer', 'Krim pelembab dan pelindung barrier kulit'),
('Krim Malam Klinik', 'Krim perawatan malam dengan resep dokter'),
('Pembersih Wajah', 'Facial wash dan cleansing milk klinik'),
('Suplemen Kulit', 'Vitamin dan suplemen antioksidan internal');

INSERT INTO staf (kode_staf, nama_lengkap, jabatan, no_telepon, email, username, password_hash, role) VALUES
('STF-001', 'Ryu Adhana', 'Super Admin', '08123456701', 'ryu@glowskin.com', 'ryuadmin', 'hash123', 'super_admin'),
('STF-002', 'Siti Rahma', 'Resepsionis', '08123456702', 'siti@glowskin.com', 'sitiresep', 'hash123', 'resepsionis'),
('STF-003', 'Budi Santoso', 'Apoteker', '08123456703', 'budi@glowskin.com', 'budiapotek', 'hash123', 'apoteker');

-- ========================================================
-- 2. DATA DUMMY: SUPPLIER & DOKTER
-- ========================================================
INSERT INTO supplier (nama_supplier, alamat, no_telepon, email, kontak_person) VALUES
('PT Beauty Cosmeindo', 'Kawasan Industri Jababeka, Bekasi', '021-8901234', 'info@cosmeindo.com', 'Bapak Andi'),
('CV Medika Skincare', 'Pulo Gadung, Jakarta Timur', '021-4701234', 'sales@medikaskin.com', 'Ibu Dewi');

INSERT INTO dokter (kode_dokter, nama_lengkap, id_spesialisasi, no_str, no_telepon, email) VALUES
('DKT-001', 'dr. Sarah Sp.KK', 1, 'STR-1122334455', '08119876541', 'sarah.spkk@glowskin.com'),
('DKT-002', 'dr. Adrian', 3, 'STR-5544332211', '08119876542', 'adrian.med@glowskin.com');

-- ========================================================
-- 3. DATA DUMMY: LAYANAN/TREATMENT & OBAT/SKINCARE
-- ========================================================
INSERT INTO layanan (kode_layanan, nama_layanan, id_jenis_layanan, deskripsi, harga, durasi_menit) VALUES
('LYN-001', 'Consultation with Dermatologist', 1, 'Konsultasi masalah kulit mendalam dengan dokter spesialis', 150000.00, 20),
('LYN-002', 'GlowSkin Facial Laser Treatment', 4, 'Perawatan laser untuk mencerahkan dan menghilangkan flek hitam', 750000.00, 45),
('LYN-003', 'Acne Peel Treatment', 2, 'Pengelupasan kimia aman untuk meredakan jerawat meradang', 350000.00, 30),
('LYN-004', 'Meso Anti-Aging', 3, 'Injeksi vitamin dan antioksidan untuk mengencangkan kerutan wajah', 900000.00, 40);

INSERT INTO obat (kode_obat, nama_obat, id_kategori, satuan, harga_jual, harga_beli, stok, stok_minimum) VALUES
('OBT-001', 'Retinol Night Cream 0.5%', 3, 'tube', 120000.00, 80000.00, 45, 10),
('OBT-002', 'Vitamin C Brightening Serum', 1, 'bottle', 180000.00, 110000.00, 60, 15),
('OBT-003', 'Sunscreen SPF 50 PA++++', 2, 'tube', 95000.00, 65000.00, 80, 20),
('OBT-004', 'Acne Facial Wash Salicylic Acid', 4, 'pcs', 75000.00, 45000.00, 5, 10), -- Stok kritis untuk ngetes alert!
('OBT-005', 'Collagen Glow Capsule', 5, 'bottle', 250000.00, 175000.00, 35, 10),
('GS-CLS-001', 'Gentle Foaming Cleanser', 4, 'pcs', 185000.00, 120000.00, 85, 10),
('GS-SRM-012', 'Brightening Vitamin C Serum', 1, 'bottle', 320000.00, 200000.00, 45, 15),
('GS-SUN-005', 'UV Shield Daily Sunscreen', 2, 'tube', 215000.00, 140000.00, 8, 10),
('GS-MST-021', 'Hyaluronic Acid Gel', 2, 'tube', 245000.00, 160000.00, 92, 15);

-- JADWAL DOKTER
INSERT INTO jadwal_dokter (id_dokter, id_hari, jam_mulai, jam_selesai, kuota_pasien) VALUES
(1, 1, '10:00:00', '14:00:00', 15),
(1, 3, '10:00:00', '14:00:00', 15),
(2, 2, '13:00:00', '17:00:00', 20),
(2, 4, '13:00:00', '17:00:00', 20);

-- ========================================================
-- 4. DATA DUMMY GENERATOR MASAL: PASIEN (25 Baris Data)
-- ========================================================
INSERT INTO pasien (kode_pasien, nama_lengkap, nik, tanggal_lahir, id_jenis_kelamin, id_golongan_darah, id_status_pernikahan, alamat, no_telepon, email, alergi) VALUES
('GS-10001', 'Adinda Kirana', '3216012345670001', '2000-05-14', 2, 1, 1, 'Jl. Kemang Raya No. 12, Jakarta Selatan', '08129999001', 'adinda@gmail.com', 'Tidak ada'),
('GS-10002', 'Bambang Tri', '3216012345670002', '1995-08-22', 1, 4, 2, 'Perumahan Galaxy, Bekasi Barat', '08129999002', 'bambang@yahoo.com', 'Alergi Seafood'),
('GS-10003', 'Citra Lestari', '3216012345670003', '1998-12-01', 2, 2, 1, 'Apartemen Kalibata City Tower Nusantara', '08129999003', 'citra@gmail.com', 'Tidak ada'),
('GS-10004', 'Dedi Mulyadi', '3216012345670004', '1990-02-11', 1, 3, 2, 'Jl. Margonda Raya No. 45, Depok', '08129999004', 'dedi@gmail.com', 'Alergi Debu'),
('GS-10005', 'Eka Putri', '3216012345670005', '2002-07-19', 2, 1, 1, 'Perumahan Harapan Indah, Bekasi', '08129999005', 'eka@gmail.com', 'Kandungan Alkohol tinggi'),
('GS-10006', 'Fajar Ramadhan', '3216012345670006', '1993-10-30', 1, 4, 2, 'Jl. Dago No. 100, Bandung', '08129999006', 'fajar@gmail.com', 'Tidak ada'),
('GS-10007', 'Gisella Anastasia', '3216012345670007', '1991-11-16', 2, 2, 3, 'Menteng, Jakarta Pusat', '08129999007', 'gisel@gmail.com', 'Tidak ada'),
('GS-10008', 'Hendra Wijaya', '3216012345670008', '1988-04-05', 1, 1, 2, 'Tebet Timur, Jakarta Selatan', '08129999008', 'hendra@gmail.com', 'Alergi Kacang'),
('GS-10009', 'Indah Permatasari', '3216012345670009', '1997-01-25', 2, 3, 2, 'Bintaro Jaya Sektor 7, Tangerang Selatan', '08129999009', 'indah@gmail.com', 'Tidak ada'),
('GS-10010', 'Joko Susilo', '3216012345670010', '1985-09-12', 1, 4, 2, 'Kelapa Gading, Jakarta Utara', '08129999010', 'joko@gmail.com', 'Tidak ada'),
('GS-10011', 'Kartika Putri', '3216012345670011', '1994-06-03', 2, 2, 2, 'Rawamangun, Jakarta Timur', '08129999011', 'kartika@gmail.com', 'Tidak ada'),
('GS-10012', 'Lukman Hakim', '3216012345670012', '1992-03-14', 1, 1, 1, 'Kuningan, Jakarta Selatan', '08129999012', 'lukman@gmail.com', 'Tidak ada'),
('GS-10013', 'Megawati', '3216012345670013', '1980-01-23', 2, 4, 3, 'Kebagusan, Jakarta Selatan', '08129999013', 'mega@gmail.com', 'Tidak ada'),
('GS-10014', 'Nadiem Makarim', '3216012345670014', '1984-07-04', 1, 3, 2, 'Kebayoran Baru, Jakarta Selatan', '08129999014', 'nadiem@gmail.com', 'Tidak ada'),
('GS-10015', 'Olivia Jensen', '3216012345670015', '1999-10-10', 2, 1, 1, 'Pondok Indah, Jakarta Selatan', '08129999015', 'olivia@gmail.com', 'Sulfur'),
('GS-10016', 'Prabowo Subianto', '3216012345670016', '1975-10-17', 1, 4, 3, 'Kertanegara, Jakarta Selatan', '08129999016', 'prabowo@gmail.com', 'Tidak ada'),
('GS-10017', 'Queenara', '3216012345670017', '2005-03-21', 2, 2, 1, 'Cibubur, Depok', '08129999017', 'queen@gmail.com', 'Tidak ada'),
('GS-10018', 'Raffi Ahmad', '3216012345670018', '1987-02-17', 1, 1, 2, 'Andara, Depok', '08129999018', 'raffi@gmail.com', 'Tidak ada'),
('GS-10019', 'Siti Nurhaliza', '3216012345670019', '1989-01-11', 2, 4, 2, 'Kramat Jati, Jakarta Timur', '08129999019', 'siti_nur@gmail.com', 'Tidak ada'),
('GS-10020', 'Taufik Hidayat', '3216012345670020', '1986-08-10', 1, 3, 2, 'Penggilingan, Jakarta Timur', '08129999020', 'taufik@gmail.com', 'Tidak ada'),
('GS-10021', 'Ussy Sulistiawaty', '3216012345670021', '1990-07-13', 2, 2, 2, 'Cinere, Depok', '08129999021', 'ussy@gmail.com', 'Tidak ada'),
('GS-10022', 'Vino G Bastian', '3216012345670022', '1982-04-11', 1, 1, 2, 'Permata Hijau, Jakarta Barat', '08129999022', 'vino@gmail.com', 'Tidak ada'),
('GS-10023', 'Wulan Guritno', '3216012345670023', '1981-04-14', 2, 4, 3, 'Kemang, Jakarta Selatan', '08129999023', 'wulan@gmail.com', 'Tidak ada'),
('GS-10024', 'Xavier', '3216012345670024', '2004-11-09', 1, 2, 1, 'Serpong, Tangerang Selatan', '08129999024', 'xavier@gmail.com', 'Tidak ada'),
('GS-10025', 'Yuni Shara', '3216012345670025', '1978-06-03', 2, 1, 3, 'Cilandak, Jakarta Selatan', '08129999025', 'yuni@gmail.com', 'Tidak ada');

-- ========================================================
-- 5. DATA DUMMY SEJARAH KUNJUNGAN PASIEN (20 Baris Data)
-- ========================================================
INSERT INTO kunjungan (kode_kunjungan, id_pasien, id_dokter, id_staf, id_status, tanggal_kunjungan, waktu_daftar, keluhan_utama, no_antrian) VALUES
('KNJ-20260601-001', 1, 1, 2, 3, '2026-06-01', '09:00:00', 'Wajah kusam dan flek hitam membandel', 1),
('KNJ-20260601-002', 2, 2, 2, 3, '2026-06-01', '10:15:00', 'Jerawat batu parah di pipi kanan', 1),
('KNJ-20260602-001', 3, 1, 2, 3, '2026-06-02', '09:30:00', 'Kulit kering sampai mengelupas', 1),
('KNJ-20260603-001', 4, 2, 2, 3, '2026-06-03', '14:00:00', 'Kerutan halus di sekitar mata', 1),
('KNJ-20260604-001', 5, 1, 2, 3, '2026-06-04', '11:00:00', 'Bruntusan di area dahi', 1),
('KNJ-20260605-001', 6, 2, 2, 3, '2026-06-05', '15:30:00', 'Mau konsultasi perawatan laser', 1),
('KNJ-20260606-001', 7, 1, 2, 3, '2026-06-06', '09:00:00', 'Bekas jerawat merah (PIE)', 1),
('KNJ-20260606-002', 8, 2, 2, 3, '2026-06-06', '13:00:00', 'Komedo hitam padat di hidung', 2),
('KNJ-20260607-001', 9, 1, 2, 3, '2026-06-07', '10:00:00', 'Kulit berminyak parah berlebih', 1),
('KNJ-20260608-001', 10, 2, 2, 3, '2026-06-08', '16:00:00', 'Kantung mata hitam mengoncol', 1),
('KNJ-20260609-001', 11, 1, 2, 3, '2026-06-09', '11:30:00', 'Alergi setelah pakai kosmetik pasar', 1),
('KNJ-20260610-001', 12, 2, 2, 3, '2026-06-10', '13:15:00', 'Kerutan di dahi', 1),
('KNJ-20260611-001', 13, 1, 2, 3, '2026-06-11', '09:45:00', 'Flek hitam penuaan dini', 1),
('KNJ-20260612-001', 14, 2, 2, 3, '2026-06-12', '14:30:00', 'Wajah sensitif gampang merah', 1),
('KNJ-20260612-002', 15, 1, 2, 3, '2026-06-12', '10:20:00', 'Jerawat hormonal bulanan', 2),
('KNJ-20260613-001', 16, 2, 2, 3, '2026-06-13', '15:00:00', 'Kulit kendor leher', 1),
('KNJ-20260614-001', 17, 1, 2, 3, '2026-06-14', '09:00:00', 'Bruntusan pasir semuka', 1),
('KNJ-20260615-001', 18, 2, 2, 1, CURDATE(), '08:30:00', 'Treatment bulanan rutin laser', 1), -- Antrean hari ini
('KNJ-20260615-002', 19, 1, 2, 1, CURDATE(), '09:15:00', 'Konsultasi jerawat pecah', 1), -- Antrean hari ini
('KNJ-20260615-003', 20, 1, 2, 1, CURDATE(), '10:00:00', 'Flek hitam akibat matahari', 2); -- Antrean hari ini

-- ========================================================
-- 6. DATA DUMMY: REKAM MEDIS & LAYANAN (40 Baris Data)
-- ========================================================
INSERT INTO rekam_medis (id_kunjungan, anamnesis, diagnosa, tindakan) VALUES
(1, 'Kulit terpapar sinar UV tinggi tanpa proteksi', 'Melasma epidermal', 'Laser toning wajah'),
(2, 'Sering konsumsi makanan manis berminyak', 'Acne Vulgaris Grade 3', 'Injeksi acne + peeling'),
(3, 'Skin barrier rusak akibat over-eksfoliasi', 'Xerosis Cutis', 'Meso Hydrating injection'),
(4, 'Faktor usia dan penurunan produksi kolagen', 'Skin Aging Mild', 'Meso Botox microinjection'),
(5, 'Sumbatan pori-pori komedogenik', 'Acne Comedonal', 'Facial ekstraksi + peeling'),
(6, 'Keinginan mencerahkan warna kulit global', 'Hiperpigmentasi pasca-inflamasi', 'Laser glow rejuve'),
(7, 'Eritema bekas jerawat lama meradang', 'Post-Inflammatory Erythema', 'Laser Vascular treatment'),
(8, 'Produksi sebum tinggi area T-Zone', 'Open Comedones', 'Deep cleansing facial therapy'),
(9, 'Kelenjar minyak overaktif', 'Seborrhea', 'Peeling Salicylic Acid 20%'),
(10, 'Sirkulasi darah area mata kurang lancar', 'Periorbital Hyperpigmentation', 'Eye Meso Treatment'),
(11, 'Kontak dermatitis akibat kosmetik merkuri', 'Dermatitis Kontak Alergika', 'Pemberian krim steroid topikal'),
(12, 'Kontraksi otot ekspresi dahi berlebih', 'Dynamic Wrinkles Forehead', 'Botox Treatment'),
(13, 'Penuaan aktinik akibat matahari kronis', 'Solar Lentigines', 'Laser spot removal'),
(14, 'Barrier kulit tipis hipersensitif', 'Sensitive Skin Syndrome', 'Soothing facial treatment'),
(15, 'Siklus menstruasi tidak teratur', 'Acne Papulopustular', 'Peeling acne + Krim racikan');

INSERT INTO detail_layanan (id_kunjungan, id_layanan, jumlah, harga_satuan, subtotal) VALUES
(1, 1, 1, 150000.00, 150000.00), (1, 2, 1, 750000.00, 750000.00),
(2, 1, 1, 150000.00, 150000.00), (2, 3, 1, 350000.00, 350000.00),
(3, 1, 1, 150000.00, 150000.00), (3, 4, 1, 900000.00, 900000.00),
(4, 1, 1, 150000.00, 150000.00), (4, 4, 1, 900000.00, 900000.00),
(5, 1, 1, 150000.00, 150000.00), (5, 3, 1, 350000.00, 350000.00),
(6, 1, 1, 150000.00, 150000.00), (6, 2, 1, 750000.00, 750000.00),
(7, 1, 1, 150000.00, 150000.00), (7, 2, 1, 750000.00, 750000.00),
(8, 1, 1, 150000.00, 150000.00), (8, 3, 1, 350000.00, 350000.00),
(9, 1, 1, 150000.00, 150000.00), (9, 3, 1, 350000.00, 350000.00),
(10, 1, 1, 150000.00, 150000.00), (10, 4, 1, 900000.00, 900000.00),
(11, 1, 1, 150000.00, 150000.00), (12, 1, 1, 150000.00, 150000.00),
(13, 1, 1, 150000.00, 150000.00), (14, 1, 1, 150000.00, 150000.00),
(15, 1, 1, 150000.00, 150000.00);

-- ========================================================
-- 7. DATA DUMMY: RESEP OBAT & PEMBAYARAN (30 Baris Data)
-- ========================================================
INSERT INTO resep_obat (id_kunjungan, id_obat, jumlah, aturan_pakai, harga_satuan, subtotal) VALUES
(1, 1, 1, 'Malam, tipis-tipis seminggu 3x', 120000.00, 120000.00), (1, 3, 1, 'Pagi & Siang hari wajib re-apply', 95000.00, 95000.00),
(2, 4, 1, '2x sehari saat cuci muka basah', 75000.00, 75000.00),
(3, 2, 1, 'Pagi & Malam sebelum pelembab', 180000.00, 180000.00), (3, 3, 1, 'Pagi hari setelah moisturizer', 95000.00, 95000.00),
(4, 5, 2, '1x sehari sesudah makan pagi', 250000.00, 500000.00),
(5, 4, 1, '2x sehari cuci muka lembut', 75000.00, 75000.00),
(6, 1, 1, 'Malam hari sebelum tidur', 120000.00, 120000.00), (6, 2, 1, 'Pagi hari mencerahkan', 180000.00, 180000.00),
(7, 2, 1, 'Pagi hari anti kemerahan', 180000.00, 180000.00),
(8, 4, 1, '2x sehari pembersih sebum', 75000.00, 75000.00),
(9, 4, 1, '2x sehari kontrol minyak', 75000.00, 75000.00),
(10, 5, 1, '1x sehari nutrisi kolagen', 250000.00, 250000.00),
(15, 1, 1, 'Malam untuk acne hormonal', 120000.00, 120000.00);

INSERT INTO pembayaran (kode_pembayaran, id_kunjungan, total_layanan, total_obat, grand_total, id_metode, status_bayar, tanggal_bayar) VALUES
('PAY-20260601-001', 1, 900000.00, 215000.00, 1115000.00, 4, 'lunas', '2026-06-01 11:00:00'),
('PAY-20260601-002', 2, 500000.00, 75000.00, 575000.00, 1, 'lunas', '2026-06-01 12:30:00'),
('PAY-20260602-001', 3, 1050000.00, 275000.00, 1325000.00, 4, 'lunas', '2026-06-02 11:15:00'),
('PAY-20260603-001', 4, 1050000.00, 500000.00, 1550000.00, 2, 'lunas', '2026-06-03 16:20:00'),
('PAY-20260604-001', 5, 500000.00, 75000.00, 575000.00, 4, 'lunas', '2026-06-04 13:00:00'),
('PAY-20260605-001', 6, 900000.00, 300000.00, 1200000.00, 1, 'lunas', '2026-06-05 17:45:00'),
('PAY-20260606-001', 7, 900000.00, 180000.00, 1080000.00, 4, 'lunas', '2026-06-06 11:00:00'),
('PAY-20260606-002', 8, 500000.00, 75000.00, 575000.00, 2, 'lunas', '2026-06-06 14:50:00'),
('PAY-20260607-001', 9, 500000.00, 75000.00, 575000.00, 4, 'lunas', '2026-06-07 12:00:00'),
('PAY-20260608-001', 10, 1050000.00, 250000.00, 1300000.00, 1, 'lunas', '2026-06-08 18:10:00'),
('PAY-20260609-001', 11, 150000.00, 0.00, 150000.00, 4, 'lunas', '2026-06-09 13:00:00'),
('PAY-20260610-001', 12, 150000.00, 0.00, 150000.00, 1, 'lunas', '2026-06-10 14:30:00'),
('PAY-20260611-001', 13, 150000.00, 0.00, 150000.00, 2, 'lunas', '2026-06-11 11:15:00'),
('PAY-20260612-001', 14, 150000.00, 0.00, 150000.00, 4, 'lunas', '2026-06-12 16:20:00'),
('PAY-20260612-002', 15, 150000.00, 120000.00, 270000.00, 4, 'lunas', '2026-06-12 12:00:00');

-- STOK MASUK LOG
INSERT INTO stok_masuk (id_obat, id_supplier, jumlah, harga_beli, tanggal_masuk, no_faktur) VALUES
(1, 1, 50, 80000.00, '2026-05-20', 'FKT-COS-9901'),
(2, 1, 50, 110000.00, '2026-05-20', 'FKT-COS-9902'),
(3, 2, 100, 65000.00, '2026-05-25', 'FKT-MED-2201');

-- ========================================================
-- [DOKUMENTASI UAS: FUNGSI AGREGAT (SUM, AVG, MAX, MIN, COUNT)]
-- ========================================================
/*
-- AGGREGATE 1: SUM — Total pendapatan bulan ini
SELECT SUM(grand_total) AS total_pendapatan_bulan
FROM pembayaran
WHERE status_bayar = 'lunas'
AND MONTH(tanggal_bayar) = MONTH(CURDATE())
AND YEAR(tanggal_bayar) = YEAR(CURDATE());

-- AGGREGATE 2: AVG — Rata-rata biaya kunjungan
SELECT AVG(grand_total) AS rata_rata_biaya
FROM pembayaran
WHERE status_bayar = 'lunas';

-- AGGREGATE 3: MAX — Transaksi tertinggi sepanjang waktu
SELECT MAX(grand_total) AS transaksi_tertinggi
FROM pembayaran
WHERE status_bayar = 'lunas';

-- AGGREGATE 4: MIN — Stok obat terendah (alert restock)
SELECT MIN(stok) AS stok_terendah, 
       nama_obat 
FROM obat 
WHERE is_active = TRUE
GROUP BY nama_obat
ORDER BY stok_terendah ASC
LIMIT 5;

-- AGGREGATE 5: COUNT — Jumlah kunjungan per dokter bulan ini
SELECT d.nama_lengkap, COUNT(k.id_kunjungan) AS jumlah_kunjungan
FROM dokter d
LEFT JOIN kunjungan k ON d.id_dokter = k.id_dokter
    AND MONTH(k.tanggal_kunjungan) = MONTH(CURDATE())
    AND YEAR(k.tanggal_kunjungan) = YEAR(CURDATE())
GROUP BY d.id_dokter, d.nama_lengkap
ORDER BY jumlah_kunjungan DESC;
*/


USE glowskin_db;

-- ========================================================
-- [SS-LAPORAN: BAB VII - 5 VIEW LAPORAN MANAJEMEN]
-- ========================================================

-- 📊 LAPORAN 1: Laporan Kunjungan Bulanan
CREATE OR REPLACE VIEW v_laporan_kunjungan_bulanan AS
SELECT 
    DATE_FORMAT(k.tanggal_kunjungan, '%Y-%m') AS bulan,
    COUNT(*) AS total_kunjungan,
    COUNT(DISTINCT k.id_pasien) AS pasien_unik,
    SUM(CASE WHEN sk.nama = 'Selesai' THEN 1 ELSE 0 END) AS selesai,
    SUM(CASE WHEN sk.nama = 'Batal' THEN 1 ELSE 0 END) AS batal,
    ROUND(SUM(CASE WHEN sk.nama = 'Selesai' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS persen_selesai
FROM kunjungan k
JOIN ref_status_kunjungan sk ON k.id_status = sk.id
GROUP BY DATE_FORMAT(k.tanggal_kunjungan, '%Y-%m')
ORDER BY bulan DESC;


-- 📊 LAPORAN 2: Layanan Terlaris
CREATE OR REPLACE VIEW v_laporan_layanan_terlaris AS
SELECT 
    l.kode_layanan,
    l.nama_layanan,
    jl.nama AS jenis,
    COUNT(dl.id_detail) AS jumlah_transaksi,
    SUM(dl.subtotal) AS total_pendapatan,
    ROUND(AVG(dl.subtotal), 0) AS rata_rata_per_transaksi
FROM layanan l
JOIN ref_jenis_layanan jl ON l.id_jenis_layanan = jl.id
LEFT JOIN detail_layanan dl ON l.id_layanan = dl.id_layanan
GROUP BY l.id_layanan, l.kode_layanan, l.nama_layanan, jl.nama
ORDER BY jumlah_transaksi DESC
LIMIT 10;


-- 📊 LAPORAN 3: Pasien Teraktif
CREATE OR REPLACE VIEW v_laporan_pasien_teraktif AS
SELECT 
    p.kode_pasien,
    p.nama_lengkap,
    fn_hitung_umur(p.tanggal_lahir) AS umur,
    COUNT(k.id_kunjungan) AS total_kunjungan,
    SUM(pb.grand_total) AS total_belanja,
    MAX(k.tanggal_kunjungan) AS kunjungan_terakhir,
    MIN(k.tanggal_kunjungan) AS kunjungan_pertama
FROM pasien p
JOIN kunjungan k ON p.id_pasien = k.id_pasien
LEFT JOIN pembayaran pb ON k.id_kunjungan = pb.id_kunjungan AND pb.status_bayar = 'lunas'
GROUP BY p.id_pasien, p.kode_pasien, p.nama_lengkap, p.tanggal_lahir
ORDER BY total_kunjungan DESC
LIMIT 10;


-- 📊 LAPORAN 4: Stok Minimum (Alert Restock)
CREATE OR REPLACE VIEW v_laporan_stok_minimum AS
SELECT 
    o.kode_obat,
    o.nama_obat,
    ko.nama_kategori,
    o.stok AS stok_saat_ini,
    o.stok_minimum,
    (o.stok_minimum - o.stok) AS perlu_restock,
    o.harga_beli,
    CASE 
        WHEN o.stok = 0 THEN '🔴 HABIS'
        WHEN o.stok <= o.stok_minimum * 0.5 THEN '🟠 KRITIS'
        ELSE '🟡 RENDAH'
    END AS status_alert
FROM obat o
JOIN kategori_obat ko ON o.id_kategori = ko.id
WHERE o.stok <= o.stok_minimum AND o.is_active = TRUE
ORDER BY o.stok ASC;


-- 📊 LAPORAN 5: Kategori & Status Pasien (VIP / Reguler)
-- Kolom 'kategori' diisi manual oleh Admin dari dashboard, atau otomatis naik
-- ke 'vip' jika total kunjungan >= 5. Default saat daftar: 'reguler'.
CREATE OR REPLACE VIEW v_laporan_status_pasien AS
SELECT 
    p.kode_pasien AS id_pasien,
    p.nama_lengkap AS nama_pasien,
    CASE
        WHEN p.kategori = 'vip' THEN 'VIP'
        WHEN p.kategori = 'member' THEN 'Member'
        ELSE 'Reguler'
    END AS kategori_pasien,
    CONCAT(COUNT(k.id_kunjungan), ' Kali') AS total_kunjungan,
    CASE
        WHEN p.no_telepon IS NOT NULL AND p.no_telepon != '' THEN 'Aktif'
        ELSE 'Tidak Aktif'
    END AS status_akun
FROM pasien p
JOIN kunjungan k ON p.id_pasien = k.id_pasien
GROUP BY p.id_pasien, p.kode_pasien, p.nama_lengkap, p.kategori, p.no_telepon
ORDER BY p.kategori DESC, COUNT(k.id_kunjungan) DESC, p.kode_pasien ASC;

/*
SELECT * FROM v_laporan_kunjungan_bulanan;
SELECT * FROM v_laporan_layanan_terlaris;
SELECT * FROM v_laporan_pasien_teraktif;
SELECT * FROM v_laporan_stok_minimum;
SELECT * FROM v_laporan_status_pasien;
*/


-- 🖥️ DASHBOARD 1: VIEW UNTUK SUMMARY CARD ADMIN
CREATE OR REPLACE VIEW v_dashboard_admin_cards AS
SELECT 
    (SELECT COUNT(*) FROM pasien) AS total_pasien,
    (SELECT COUNT(*) FROM kunjungan WHERE tanggal_kunjungan = CURDATE()) AS kunjungan_hari_ini,
    (SELECT COALESCE(SUM(grand_total), 0) FROM pembayaran WHERE status_bayar = 'lunas' AND MONTH(tanggal_bayar) = MONTH(CURDATE()) AND YEAR(tanggal_bayar) = YEAR(CURDATE())) AS pendapatan_bulan_ini,
    (SELECT COUNT(*) FROM obat WHERE stok <= stok_minimum AND is_active = TRUE) AS obat_perlu_restock;

-- 🖥️ DASHBOARD 1: VIEW UNTUK TABEL ANTREAN GLOBAL ADMIN
CREATE OR REPLACE VIEW v_dashboard_admin_antrian AS
SELECT 
    k.no_antrian,
    p.kode_pasien,
    p.nama_lengkap,
    d.nama_lengkap AS dokter,
    k.keluhan_utama,
    sk.nama AS status,
    k.waktu_daftar
FROM kunjungan k
JOIN pasien p ON k.id_pasien = p.id_pasien
JOIN dokter d ON k.id_dokter = d.id_dokter
JOIN ref_status_kunjungan sk ON k.id_status = sk.id
WHERE k.tanggal_kunjungan = CURDATE()
ORDER BY k.no_antrian;


-- 🖥️ DASHBOARD 2: STORED PROCEDURE SUMMARY CARD DOKTER (Berdasarkan ID Dokter)
DELIMITER //
CREATE PROCEDURE sp_dashboard_dokter_cards(IN p_id_dokter INT)
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM kunjungan WHERE id_dokter = p_id_dokter AND tanggal_kunjungan = CURDATE()) AS pasien_hari_ini,
        (SELECT COUNT(*) FROM kunjungan WHERE id_dokter = p_id_dokter AND tanggal_kunjungan = CURDATE() AND id_status = 1) AS menunggu_antrian,
        (SELECT COUNT(*) FROM kunjungan WHERE id_dokter = p_id_dokter AND tanggal_kunjungan = CURDATE() AND id_status = 3) AS sudah_diperiksa,
        (SELECT COUNT(DISTINCT id_pasien) FROM kunjungan WHERE id_dokter = p_id_dokter AND MONTH(tanggal_kunjungan) = MONTH(CURDATE()) AND YEAR(tanggal_kunjungan) = YEAR(CURDATE())) AS pasien_bulan_ini;
END //
DELIMITER ;

-- 🖥️ DASHBOARD 2: STORED PROCEDURE TABEL ANTREAN DOKTER (Berdasarkan ID Dokter)
DELIMITER //
CREATE PROCEDURE sp_dashboard_dokter_antrian(IN p_id_dokter INT)
BEGIN
    SELECT 
        k.no_antrian,
        p.nama_lengkap,
        fn_hitung_umur(p.tanggal_lahir) AS umur,
        jk.nama AS jenis_kelamin,
        k.keluhan_utama,
        sk.nama AS status
    FROM kunjungan k
    JOIN pasien p ON k.id_pasien = p.id_pasien
    JOIN ref_jenis_kelamin jk ON p.id_jenis_kelamin = jk.id
    JOIN ref_status_kunjungan sk ON k.id_status = sk.id
    WHERE k.id_dokter = p_id_dokter AND k.tanggal_kunjungan = CURDATE()
    ORDER BY k.no_antrian;
END //
DELIMITER ;

/*
-- Cek Data Dashboard Admin
SELECT * FROM v_dashboard_admin_cards;
SELECT * FROM v_dashboard_admin_antrian;

-- Cek Data Dashboard Dokter (Misal untuk id_dokter = 1 alias dr. Sarah)
CALL sp_dashboard_dokter_cards(1);
CALL sp_dashboard_dokter_antrian(1);
*/


-- ========================================================
-- [SS-LAPORAN: BAB V - TCL TRANSACTION DEMO (COMMIT & ROLLBACK)]
-- ========================================================
/*
-- Skenario 1: COMMIT (Transaksi pendaftaran kunjungan berhasil)
START TRANSACTION;

-- Step 1: Daftarkan kunjungan baru
INSERT INTO kunjungan (kode_kunjungan, id_pasien, id_dokter, id_staf, tanggal_kunjungan, 
                       waktu_daftar, keluhan_utama, no_antrian)
VALUES ('KNJ-DEMO-TCL-OK', 1, 1, 1, CURDATE(), CURRENT_TIME(), 'Wajah bruntusan parah', 3);

-- Simpan ID kunjungan yang baru dimasukkan ke variabel session
SET @last_kunjungan_id = LAST_INSERT_ID();

-- Step 2: Tambahkan detail layanan
INSERT INTO detail_layanan (id_kunjungan, id_layanan, jumlah, harga_satuan, subtotal)
VALUES (@last_kunjungan_id, 1, 1, 150000.00, 150000.00);

-- Step 3: Tambahkan pembayaran status belum lunas
INSERT INTO pembayaran (kode_pembayaran, id_kunjungan, total_layanan, grand_total, id_metode)
VALUES ('PAY-DEMO-TCL-OK', @last_kunjungan_id, 150000.00, 150000.00, 1);

-- Simpan perubahan secara permanen
COMMIT;

-- Skenario 2: ROLLBACK (Transaksi dibatalkan karena ada kegagalan)
START TRANSACTION;
INSERT INTO kunjungan (kode_kunjungan, id_pasien, id_dokter, id_staf, tanggal_kunjungan, 
                       waktu_daftar, keluhan_utama, no_antrian)
VALUES ('KNJ-DEMO-TCL-FAIL', 9999, 1, 1, CURDATE(), CURRENT_TIME(), 'Flek Hitam', 99);
-- Query di atas akan memicu error foreign key karena id_pasien 9999 tidak terdaftar.
-- Di program backend PHP, jika ditangkap error (exception), langsung panggil ROLLBACK;
ROLLBACK;
*/


-- ========================================================
-- [SS-LAPORAN: BAB V - CONCURRENCY & TABLE/ROW LOCKING DEMO]
-- ========================================================
/*
-- --- 1. TABLE WRITE LOCK (Mencegah modifikasi tabel oleh user lain) ---
LOCK TABLES obat WRITE;
-- Session ini bisa mengupdate data obat
UPDATE obat SET stok = stok + 10 WHERE id_obat = 1;
-- Lepaskan kunci agar user lain bisa melakukan modifikasi kembali
UNLOCK TABLES;

-- --- 2. ROW-LEVEL LOCKING (InnoDB - Mengunci baris tertentu saja) ---
START TRANSACTION;
-- Mengunci baris data obat id=1 saja agar tidak diserobot transaksi lain saat proses resep berlangsung
SELECT * FROM obat WHERE id_obat = 1 FOR UPDATE;
-- Melakukan update stok
UPDATE obat SET stok = stok - 1 WHERE id_obat = 1;
-- Melepas row lock dengan COMMIT
COMMIT;
*/


-- ========================================================
-- TABEL ULASAN PASIEN (Fitur Landing Page)
-- ========================================================
CREATE TABLE IF NOT EXISTS ulasan_pasien (
    id_ulasan       INT AUTO_INCREMENT PRIMARY KEY,
    nama_pasien     VARCHAR(100) NOT NULL,
    layanan_diambil VARCHAR(100) NOT NULL,
    rating          TINYINT NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5),
    isi_ulasan      TEXT NOT NULL,
    is_approved     BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data awal ulasan
INSERT INTO ulasan_pasien (nama_pasien, layanan_diambil, rating, isi_ulasan, is_approved) VALUES
('Adinda Kirana', 'Laser Therapy', 5, 'Sangat puas dengan Laser Therapy di GlowSkin! Bekas jerawat kemerahan saya yang sudah berbulan-bulan langsung memudar setelah 2 kali sesi. Pelayanannya sangat ramah dan profesional.', TRUE),
('Citra Lestari', 'Skincare Racikan', 5, 'Dokter Sarah sangat detail saat menganalisis kulit saya. Krim racikan dan serumnya cocok sekali di kulit sensitif saya, tidak ada efek kemerahan atau mengelupas parah. Skin barrier saya sekarang jauh lebih kuat!', TRUE),
('Fajar Ramadhan', 'Facial Treatment', 5, 'Tempatnya bersih, mewah, dan menenangkan. Facial Treatment di sini terasa seperti relaksasi total dengan pijatan yang nyaman sekali. Komedo bersih tuntas tanpa rasa sakit yang berlebih.', TRUE);


-- ========================================================
-- [SS-LAPORAN: BAB VI - BACKUP & RESTORE DOCUMENTATION]
-- ========================================================
/*
-- 1. CARA BACKUP DATABASE via Terminal/Command Prompt:
mysqldump -u root -p glowskin_db > db_glowskin_backup.sql

-- 2. CARA RESTORE DATABASE ke database baru:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS glowskin_db_restore;"
mysql -u root -p glowskin_db_restore < db_glowskin_backup.sql
*/