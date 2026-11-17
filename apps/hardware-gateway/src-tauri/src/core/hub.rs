use crate::core::models::{DeviceStatus, DeviceType, PeripheralDevice};
use parking_lot::RwLock;
use std::collections::HashMap;
use std::sync::Arc;
use std::time::Instant;

#[derive(Clone)]
pub struct DeviceHub {
    start_time: Instant,
    devices: Arc<RwLock<HashMap<String, PeripheralDevice>>>,
}

impl DeviceHub {
    pub fn new() -> Self {
        let mut initial_devices = HashMap::new();

        // نمونه‌های پیش‌فرض سخت‌افزارها برای راه‌اندازی و تست
        initial_devices.insert(
            "pos-01".to_string(),
            PeripheralDevice {
                id: "pos-01".to_string(),
                name: "کارتخوان سامان‌کیش (SEP PC-POS)".to_string(),
                device_type: DeviceType::Pos,
                driver: "SamanKishAdapter".to_string(),
                status: DeviceStatus::Online,
                connection_type: crate::core::models::ConnectionType::Serial,
                address: Some("COM3:115200".to_string()),
            },
        );

        initial_devices.insert(
            "printer-receipt-01".to_string(),
            PeripheralDevice {
                id: "printer-receipt-01".to_string(),
                name: "فیش‌پرینتر حرارتی Bixolon SRP-350".to_string(),
                device_type: DeviceType::Printer,
                driver: "EscPosAdapter".to_string(),
                status: DeviceStatus::Online,
                connection_type: crate::core::models::ConnectionType::Usb,
                address: Some("/dev/usb/lp0".to_string()),
            },
        );

        initial_devices.insert(
            "drawer-01".to_string(),
            PeripheralDevice {
                id: "drawer-01".to_string(),
                name: "کشوی پول صندق (RJ-11 Pin 2)".to_string(),
                device_type: DeviceType::Drawer,
                driver: "CashDrawerKickAdapter".to_string(),
                status: DeviceStatus::Online,
                connection_type: crate::core::models::ConnectionType::Usb,
                address: Some("via:printer-receipt-01".to_string()),
            },
        );

        initial_devices.insert(
            "scale-01".to_string(),
            PeripheralDevice {
                id: "scale-01".to_string(),
                name: "ترازوی دیجیتال رادین (Radin Scale)".to_string(),
                device_type: DeviceType::Scale,
                driver: "RadinSerialScaleAdapter".to_string(),
                status: DeviceStatus::Online,
                connection_type: crate::core::models::ConnectionType::Serial,
                address: Some("COM1:9600".to_string()),
            },
        );

        initial_devices.insert(
            "zkteco-01".to_string(),
            PeripheralDevice {
                id: "zkteco-01".to_string(),
                name: "دستگاه حضور و غیاب ZKTeco K40".to_string(),
                device_type: DeviceType::Attendance,
                driver: "ZktecoUdpAdapter".to_string(),
                status: DeviceStatus::Online,
                connection_type: crate::core::models::ConnectionType::Network,
                address: Some("192.168.1.201:4370".to_string()),
            },
        );

        Self {
            start_time: Instant::now(),
            devices: Arc::new(RwLock::new(initial_devices)),
        }
    }

    pub fn uptime_seconds(&self) -> u64 {
        self.start_time.elapsed().as_secs()
    }

    pub fn get_devices(&self) -> Vec<PeripheralDevice> {
        self.devices.read().values().cloned().collect()
    }

    pub fn get_device(&self, id: &str) -> Option<PeripheralDevice> {
        self.devices.read().get(id).cloned()
    }

    pub fn set_status(&self, id: &str, status: DeviceStatus) {
        if let Some(dev) = self.devices.write().get_mut(id) {
            dev.status = status;
        }
    }
}
