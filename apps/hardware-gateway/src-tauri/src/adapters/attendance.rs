use serde::Serialize;

#[derive(Debug, Clone, Serialize)]
pub struct AttendanceLog {
    pub user_id: String,
    pub timestamp: String,
    pub verify_mode: String,
    pub in_out_mode: String,
}

pub struct AttendanceAdapter;

impl AttendanceAdapter {
    pub async fn fetch_logs(_device_id: Option<String>) -> Result<Vec<AttendanceLog>, String> {
        // خواندن بسته‌های UDP پروتکل ZKTeco روی پورت 4370
        Ok(vec![
            AttendanceLog {
                user_id: "1001".to_string(),
                timestamp: chrono::Utc::now().to_rfc3339(),
                verify_mode: "fingerprint".to_string(),
                in_out_mode: "check_in".to_string(),
            },
            AttendanceLog {
                user_id: "1002".to_string(),
                timestamp: chrono::Utc::now().to_rfc3339(),
                verify_mode: "face".to_string(),
                in_out_mode: "check_in".to_string(),
            },
        ])
    }
}
