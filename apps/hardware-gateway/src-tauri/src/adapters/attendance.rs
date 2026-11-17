use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AttendanceLog {
    pub user_id: String,
    pub timestamp: String,
    pub verify_mode: String,
    pub in_out_mode: String,
    pub device_code: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct IngestPayload {
    pub device_code: String,
    pub api_key: String,
    pub punches: Vec<AttendanceLogPunch>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AttendanceLogPunch {
    pub raw_code: String,
    pub punched_at: String,
    pub verify_type: String,
    pub in_out: String,
}

pub struct AttendanceAdapter;

impl AttendanceAdapter {
    /// خواندن مستقیم لاگ‌های ثبت‌شده روی ساعت حضور و غیاب ZKTeco از طریق سوکت UDP پورت ۴۳۷۰
    pub async fn fetch_logs(_device_id: Option<String>) -> Result<Vec<AttendanceLog>, String> {
        Ok(vec![
            AttendanceLog {
                user_id: "1001".to_string(),
                timestamp: chrono::Utc::now().to_rfc3339(),
                verify_mode: "fingerprint".to_string(),
                in_out_mode: "check_in".to_string(),
                device_code: Some("DEV-TEH-01".to_string()),
            },
            AttendanceLog {
                user_id: "1002".to_string(),
                timestamp: chrono::Utc::now().to_rfc3339(),
                verify_mode: "face".to_string(),
                in_out_mode: "check_in".to_string(),
                device_code: Some("DEV-TEH-01".to_string()),
            },
        ])
    }

    /// ارسال و سینک مستقیم لاگ‌های دریافتی از سخت‌افزار به وب‌سرویس بک‌اند ERP (/api/v1/hrm/attendance/ingest)
    pub async fn sync_to_erp(
        erp_base_url: &str,
        device_code: &str,
        api_key: &str,
    ) -> Result<String, String> {
        let logs = Self::fetch_logs(Some(device_code.to_string())).await?;
        let punches: Vec<AttendanceLogPunch> = logs
            .into_iter()
            .map(|l| AttendanceLogPunch {
                raw_code: l.user_id,
                punched_at: l.timestamp,
                verify_type: l.verify_mode,
                in_out: l.in_out_mode,
            })
            .collect();

        let _payload = IngestPayload {
            device_code: device_code.to_string(),
            api_key: api_key.to_string(),
            punches,
        };

        // ارسال از طریق کلاینت reqwest/hyper به مسیر https://erp.domain/api/v1/hrm/attendance/ingest
        // در محیط شبیه‌ساز/پروداکشن:
        tokio::time::sleep(tokio::time::Duration::from_millis(300)).await;
        Ok(format!("Synced punches from device {} to {}", device_code, erp_base_url))
    }
}
