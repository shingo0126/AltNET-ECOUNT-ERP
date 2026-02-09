-- ============================================
-- AltNET Ecount ERP - Initial Seed Data
-- ============================================

USE altnet_ecount;

-- 초기 관리자 계정 (altnet / altnet2016!)
-- bcrypt hash of 'altnet2016!'
INSERT INTO users (username, password_hash, name, email, role, is_active) VALUES
('altnet', '$2y$10$8K1p/a0dL1LXMIgoEDFrwOBvFkhbvMJf9bBlKQo/gE6N5mJLfLhHe', '관리자', 'admin@altnet.co.kr', 'admin', 1);

-- 샘플 매출 업체
INSERT INTO companies (name, contact_person, phone, email, zipcode, address, address_detail) VALUES
('삼성전자', '김상무', '010-1234-5678', 'samsung@example.com', '06178', '서울특별시 강남구 테헤란로 212', '삼성빌딩 5층'),
('LG전자', '이부장', '010-2345-6789', 'lg@example.com', '07336', '서울특별시 영등포구 여의대로 128', 'LG트윈타워'),
('SK텔레콤', '박과장', '010-3456-7890', 'skt@example.com', '04539', '서울특별시 중구 을지로 65', 'SK T타워');

-- 샘플 매입 업체  
INSERT INTO vendors (name, contact_person, phone, email, zipcode, address, address_detail) VALUES
('부품넷', '최대리', '010-4567-8901', 'parts@example.com', '13494', '경기도 성남시 분당구 판교역로 235', '판교테크노밸리'),
('소프트웨어원', '정사원', '010-5678-9012', 'soft@example.com', '06236', '서울특별시 강남구 역삼로 180', '역삼빌딩 3층');

-- 샘플 판매 제품 코드
INSERT INTO sale_items (sort_order, name) VALUES
(1, '하드웨어 판매'),
(2, '소프트웨어 라이선스'),
(3, '기술 서비스'),
(4, '유지보수 계약'),
(5, '컨설팅');
