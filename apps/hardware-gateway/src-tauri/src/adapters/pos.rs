use chrono::Utc;
use serde::{Deserialize, Serialize};

#[derive(Debug, Deserialize)]
pub struct ChargeRequest {
    pub device_id: Option<String>,
    pub amount: u64,
    pub invoice_number: String,
    pub payer_mobile: Option<String>,
    pub timeout_seconds: Option<u64>,
}

#[derive(Debug, Serialize)]
pub struct ChargeResponse {
    pub success: bool,
    pub masked_pan: String,
    pub rrn: String,
    pub trace_no: String,
    pub terminal_id: String,
    pub transaction_time: String,
    pub message: String,
}

pub struct PosAdapter;

impl PosAdapter {
    pub async fn process_charge(req: ChargeRequest) -> Result<ChargeResponse, String> {
        // شبیه‌سازی پروتکل سریال/شبکه پایانه فروش (PC-POS)
        // در محیط پروداکشن، بایت‌های فریم تراکنش خرید ارسال و منتظر کشیدن کارت و ورود رمز می‌ماند
        tokio::time::sleep(tokio::time::Duration::from_millis(1500)).await;

        let trace = format!("{:06}", (chrono::Utc::now().timestamp_millis() % 900000) + 100000);
        let rrn = format!("{:012}", chrono::Utc::now().timestamp_nanos_opt().unwrap_or(123456789) % 1000000000000i64);

        Ok(ChargeResponse {
            success: true,
            masked_pan: "6037-99**-****-4821".to_string(),
            rrn,
            trace_no: trace,
            terminal_id: "88219034".to_string(),
            transaction_time: Utc::now().to_rfc3339(),
            message: "تراکنش خرید با موفقیت انجام شد".to_string(),
        })
    }
}
