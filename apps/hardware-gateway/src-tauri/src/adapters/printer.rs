use serde::Deserialize;

#[derive(Debug, Deserialize)]
pub struct ReceiptLine {
    pub text: String,
    pub align: Option<String>,
    pub bold: Option<bool>,
    pub double_height: Option<bool>,
}

#[derive(Debug, Deserialize)]
pub struct PrintReceiptRequest {
    pub device_id: Option<String>,
    pub lines: Vec<ReceiptLine>,
    pub qr_code: Option<String>,
    pub cut_paper: Option<bool>,
    pub open_drawer: Option<bool>,
}

pub struct PrinterAdapter;

impl PrinterAdapter {
    pub async fn print_receipt(_req: PrintReceiptRequest) -> Result<String, String> {
        // ایجاد کدهای باینری استاندارد ESC/POS:
        // ESC @ (Reset) -> GS V 66 0 (Cut paper)
        tokio::time::sleep(tokio::time::Duration::from_millis(400)).await;
        let job_id = format!("job-{}", uuid::Uuid::new_v4().simple());
        Ok(job_id)
    }

    pub async fn open_drawer(_device_id: Option<String>) -> Result<(), String> {
        // ESC p m t1 t2 (پالس ولتاژ ۲۴ ولت روی پین ۲ خروجی RJ11 پرینتر)
        tokio::time::sleep(tokio::time::Duration::from_millis(150)).await;
        Ok(())
    }
}
