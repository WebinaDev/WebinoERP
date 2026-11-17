use chrono::Utc;
use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct GatewayHealth {
    pub version: String,
    pub uptime_seconds: u64,
    pub devices_count: usize,
    pub status: String,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "lowercase")]
pub enum DeviceType {
    Pos,
    Printer,
    Drawer,
    Attendance,
    Scale,
    Scanner,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "lowercase")]
pub enum DeviceStatus {
    Online,
    Offline,
    Busy,
    Error,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "lowercase")]
pub enum ConnectionType {
    Usb,
    Serial,
    Network,
    Bluetooth,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PeripheralDevice {
    pub id: String,
    pub name: String,
    pub device_type: DeviceType,
    pub driver: String,
    pub status: DeviceStatus,
    pub connection_type: ConnectionType,
    pub address: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct GatewayResponse<T> {
    pub success: bool,
    pub code: String,
    pub message: String,
    pub data: Option<T>,
    pub device_id: Option<String>,
    pub timestamp: String,
}

impl<T> GatewayResponse<T> {
    pub fn success(data: T, message: &str, device_id: Option<String>) -> Self {
        Self {
            success: true,
            code: "OK".to_string(),
            message: message.to_string(),
            data: Some(data),
            device_id,
            timestamp: Utc::now().to_rfc3339(),
        }
    }

    pub fn error(code: &str, message: &str, device_id: Option<String>) -> Self {
        Self {
            success: false,
            code: code.to_string(),
            message: message.to_string(),
            data: None,
            device_id,
            timestamp: Utc::now().to_rfc3339(),
        }
    }
}
