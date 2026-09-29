<?php

namespace SimpleFinance;

class BankList
{
    /**
     * Danh sách các ngân hàng hỗ trợ VietQR tại Việt Nam (mã BIN chuẩn Napas)
     */
    public static function getBanks(): array
    {
        return [
            ['bin' => '970422', 'code' => 'MB',       'short_name' => 'MBBank',        'name' => 'Ngân hàng Quân Đội'],
            ['bin' => '970436', 'code' => 'VCB',      'short_name' => 'Vietcombank',    'name' => 'Ngân hàng TMCP Ngoại Thương Việt Nam'],
            ['bin' => '970407', 'code' => 'TCB',      'short_name' => 'Techcombank',    'name' => 'Ngân hàng TMCP Kỹ Thương Việt Nam'],
            ['bin' => '970418', 'code' => 'BIDV',     'short_name' => 'BIDV',           'name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'],
            ['bin' => '970415', 'code' => 'CTG',      'short_name' => 'VietinBank',     'name' => 'Ngân hàng TMCP Công Thương Việt Nam'],
            ['bin' => '970432', 'code' => 'VPB',      'short_name' => 'VPBank',         'name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'],
            ['bin' => '970416', 'code' => 'ACB',      'short_name' => 'ACB',            'name' => 'Ngân hàng TMCP Á Châu'],
            ['bin' => '970423', 'code' => 'TPB',      'short_name' => 'TPBank',         'name' => 'Ngân hàng TMCP Tiên Phong'],
            ['bin' => '970403', 'code' => 'STB',      'short_name' => 'Sacombank',      'name' => 'Ngân hàng TMCP Sài Gòn Thương Tín'],
            ['bin' => '970405', 'code' => 'VBA',      'short_name' => 'Agribank',       'name' => 'Ngân hàng Nông nghiệp và PT Nông thôn VN'],
            ['bin' => '970437', 'code' => 'HDB',      'short_name' => 'HDBank',         'name' => 'Ngân hàng TMCP Phát triển TP.HCM'],
            ['bin' => '970441', 'code' => 'VIB',      'short_name' => 'VIB',            'name' => 'Ngân hàng TMCP Quốc tế Việt Nam'],
            ['bin' => '970443', 'code' => 'SHB',      'short_name' => 'SHB',            'name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội'],
            ['bin' => '970426', 'code' => 'MSB',      'short_name' => 'MSB',            'name' => 'Ngân hàng TMCP Hàng Hải Việt Nam'],
            ['bin' => '970448', 'code' => 'OCB',      'short_name' => 'OCB',            'name' => 'Ngân hàng TMCP Phương Đông'],
            ['bin' => '970440', 'code' => 'SEAB',     'short_name' => 'SeABank',        'name' => 'Ngân hàng TMCP Đông Nam Á'],
            ['bin' => '970431', 'code' => 'EIB',      'short_name' => 'Eximbank',       'name' => 'Ngân hàng TMCP Xuất Nhập Khẩu Việt Nam'],
            ['bin' => '970449', 'code' => 'LPB',      'short_name' => 'LPBank',         'name' => 'Ngân hàng TMCP Lộc Phát Việt Nam'],
            ['bin' => '963388', 'code' => 'TIMO',     'short_name' => 'Timo',           'name' => 'Ngân hàng số Timo by BVBank'],
            ['bin' => '546034', 'code' => 'CAKE',     'short_name' => 'Cake by VPBank', 'name' => 'Ngân hàng số Cake by VPBank'],
            ['bin' => '970412', 'code' => 'PVCB',     'short_name' => 'PVcomBank',      'name' => 'Ngân hàng TMCP Đại Chúng Việt Nam'],
            ['bin' => '970428', 'code' => 'NAB',      'short_name' => 'Nam A Bank',     'name' => 'Ngân hàng TMCP Nam Á'],
            ['bin' => '970409', 'code' => 'BAB',      'short_name' => 'Bac A Bank',     'name' => 'Ngân hàng TMCP Bắc Á'],
            ['bin' => '970433', 'code' => 'VIETBANK', 'short_name' => 'Vietbank',       'name' => 'Ngân hàng TMCP Việt Nam Thương Tín'],
            ['bin' => '970438', 'code' => 'BVB',      'short_name' => 'BaoViet Bank',   'name' => 'Ngân hàng TMCP Bảo Việt'],
            ['bin' => '970452', 'code' => 'KLB',      'short_name' => 'Kienlongbank',   'name' => 'Ngân hàng TMCP Kiên Long'],
            ['bin' => '970424', 'code' => 'SHBVN',    'short_name' => 'Shinhan Bank',   'name' => 'Ngân hàng TNHH MTV Shinhan Việt Nam'],
            ['bin' => '970457', 'code' => 'WOO',      'short_name' => 'Woori Bank',     'name' => 'Ngân hàng TNHH MTV Woori Việt Nam'],
            ['bin' => '668888', 'code' => 'KBANK',    'short_name' => 'KBank',          'name' => 'Ngân hàng Kasikornbank - CN TP.HCM'],
        ];
    }

    /**
     * Tìm ngân hàng theo mã BIN
     */
    public static function findByBin(string $bin): ?array
    {
        foreach (self::getBanks() as $b) {
            if ($b['bin'] === $bin) {
                return $b;
            }
        }
        return null;
    }

    /**
     * Tạo URL mã VietQR chuẩn
     */
    public static function generateVietQrUrl(
        string $bankBin,
        string $accountNo,
        float $amount = 0,
        string $description = '',
        string $accountName = '',
        string $template = 'compact2'
    ): string {
        $bin = trim($bankBin);
        $acc = trim($accountNo);
        $baseUrl = "https://img.vietqr.io/image/{$bin}-{$acc}-{$template}.png";

        $params = [];
        if ($amount > 0) {
            $params['amount'] = (int)round($amount);
        }
        if (!empty($description)) {
            $params['addInfo'] = $description;
        }
        if (!empty($accountName)) {
            $params['accountName'] = $accountName;
        }

        if (!empty($params)) {
            $baseUrl .= '?' . http_build_query($params);
        }

        return $baseUrl;
    }
}
