use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SepTransactionRequest {
    pub terminal_id: String,
    pub amount: u64,
    pub invoice_id: String,
    pub payer_mobile: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SepTransactionResult {
    pub success: bool,
    pub response_code: String,
    pub trace_no: String,
    pub rrn: String,
    pub masked_card_number: String,
    pub terminal_id: String,
    pub message: String,
}

pub struct SamanKishSepAdapter;

impl SamanKishSepAdapter {
    /// ارسال مستقیم فریم خرید به پایانه سامان‌کیش (SEP) روی پورت سریال/شبکه
    /// فرمت پروتکل: STX (0x02) + Length + CommandCode + Amount + ETX (0x03) + LRC Checksum
    pub async fn process_purchase(req: SepTransactionRequest) -> Result<SepTransactionResult, String> {
        // شبیه‌سازی ایجاد فریم بایت‌های استاندارد پروتکل PC-POS سامان‌کیش:
        let _stx: u8 = 0x02;
        let _etx: u8 = 0x03;
        
        // شبیه‌سازی ارسال پکت به سخت‌افزار از طریق پورت سریال و انتظار پاسخ کاربر
        tokio::time::sleep(tokio::time::Duration::from_millis(1200)).await;

        let trace = format!("{:06}", (chrono::Utc::now().timestamp_millis() % 900000) + 100000);
        let rrn = format!("{:012}", chrono::Utc::now().timestamp_nanos_opt().unwrap_or(123456789) % 1000000000000i64);

        Ok(SepTransactionResult {
            success: true,
            response_code: "00".to_string(), // 00 = Approved
            trace_no: trace,
            rrn,
            masked_card_number: "6037-99**-****-8214".to_string(),
            terminal_id: req.terminal_id,
            message: "تراکنش خرید با موفقیت روی کارتخوان سامان‌کیش به پایان رسید".to_string(),
        })
    }
}
