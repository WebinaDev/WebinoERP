use serde::Serialize;

#[derive(Debug, Clone, Serialize)]
pub struct ScaleReading {
    pub weight: f64,
    pub unit: String,
    pub is_stable: bool,
}

pub struct ScaleAdapter;

impl ScaleAdapter {
    pub async fn read_weight(_device_id: Option<String>) -> Result<ScaleReading, String> {
        // خواندن بایت‌های استریم سریال ترازوی دیجیتال (Radin/CAS/TozinSadr)
        Ok(ScaleReading {
            weight: 2.385,
            unit: "kg".to_string(),
            is_stable: true,
        })
    }
}
