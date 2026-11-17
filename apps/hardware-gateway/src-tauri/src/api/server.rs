use crate::adapters::attendance::AttendanceAdapter;
use crate::adapters::pos::{ChargeRequest, PosAdapter};
use crate::adapters::printer::{PrintReceiptRequest, PrinterAdapter};
use crate::adapters::scale::ScaleAdapter;
use crate::core::hub::DeviceHub;
use crate::core::models::{GatewayHealth, GatewayResponse, PeripheralDevice};
use axum::{
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    routing::{get, post},
    Json, Router,
};
use tower_http::cors::{Any, CorsLayer};

pub fn build_router(hub: DeviceHub) -> Router {
    let cors = CorsLayer::new()
        .allow_origin(Any)
        .allow_methods(Any)
        .allow_headers(Any);

    Router::new()
        .route("/health", get(health_handler))
        .route("/devices", get(list_devices_handler))
        .route("/devices/:id/status", get(device_status_handler))
        .route("/pos/charge", post(pos_charge_handler))
        .route("/print/receipt", post(print_receipt_handler))
        .route("/cash-drawer/open", post(cash_drawer_open_handler))
        .route("/scale/weight", get(scale_weight_handler))
        .route("/attendance/logs", get(attendance_logs_handler))
        .layer(cors)
        .with_state(hub)
}

async fn health_handler(State(hub): State<DeviceHub>) -> impl IntoResponse {
    let health = GatewayHealth {
        version: "1.0.0".to_string(),
        uptime_seconds: hub.uptime_seconds(),
        devices_count: hub.get_devices().len(),
        status: "healthy".to_string(),
    };
    Json(GatewayResponse::success(health, "Webino Hardware Gateway is operational", None))
}

async fn list_devices_handler(State(hub): State<DeviceHub>) -> impl IntoResponse {
    let devices = hub.get_devices();
    Json(GatewayResponse::success(devices, "Device list retrieved", None))
}

async fn device_status_handler(
    Path(id): Path<String>,
    State(hub): State<DeviceHub>,
) -> impl IntoResponse {
    match hub.get_device(&id) {
        Some(device) => Json(GatewayResponse::success(device, "Device found", Some(id))),
        None => Json(GatewayResponse::error("NOT_FOUND", "Device not found", Some(id))),
    }
}

async fn pos_charge_handler(
    State(_hub): State<DeviceHub>,
    Json(req): Json<ChargeRequest>,
) -> impl IntoResponse {
    let dev_id = req.device_id.clone().unwrap_or_else(|| "pos-01".to_string());
    match PosAdapter::process_charge(req).await {
        Ok(res) => (
            StatusCode::OK,
            Json(GatewayResponse::success(res, "تراکنش خرید با موفقیت تکمیل شد", Some(dev_id))),
        ),
        Err(err) => (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(GatewayResponse::error("POS_ERROR", &err, Some(dev_id))),
        ),
    }
}

async fn print_receipt_handler(
    State(_hub): State<DeviceHub>,
    Json(req): Json<PrintReceiptRequest>,
) -> impl IntoResponse {
    let dev_id = req.device_id.clone().unwrap_or_else(|| "printer-receipt-01".to_string());
    match PrinterAdapter::print_receipt(req).await {
        Ok(job_id) => (
            StatusCode::OK,
            Json(GatewayResponse::success(
                serde_json::json!({ "jobId": job_id }),
                "دستور چاپ ارسال شد",
                Some(dev_id),
            )),
        ),
        Err(err) => (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(GatewayResponse::error("PRINT_ERROR", &err, Some(dev_id))),
        ),
    }
}

async fn cash_drawer_open_handler(
    State(_hub): State<DeviceHub>,
    Json(payload): Json<serde_json::Value>,
) -> impl IntoResponse {
    let dev_id = payload.get("deviceId").and_then(|v| v.as_str()).map(|s| s.to_string());
    match PrinterAdapter::open_drawer(dev_id.clone()).await {
        Ok(_) => (
            StatusCode::OK,
            Json(GatewayResponse::success(
                serde_json::json!({ "opened": true }),
                "کشوی پول باز شد",
                dev_id,
            )),
        ),
        Err(err) => (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(GatewayResponse::error("DRAWER_ERROR", &err, dev_id)),
        ),
    }
}

async fn scale_weight_handler(State(_hub): State<DeviceHub>) -> impl IntoResponse {
    match ScaleAdapter::read_weight(None).await {
        Ok(reading) => (
            StatusCode::OK,
            Json(GatewayResponse::success(reading, "وزن خوانده شد", Some("scale-01".to_string()))),
        ),
        Err(err) => (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(GatewayResponse::error("SCALE_ERROR", &err, Some("scale-01".to_string()))),
        ),
    }
}

async fn attendance_logs_handler(State(_hub): State<DeviceHub>) -> impl IntoResponse {
    match AttendanceAdapter::fetch_logs(None).await {
        Ok(logs) => (
            StatusCode::OK,
            Json(GatewayResponse::success(
                logs,
                "لاگ تردد دریافت شد",
                Some("zkteco-01".to_string()),
            )),
        ),
        Err(err) => (
            StatusCode::INTERNAL_SERVER_ERROR,
            Json(GatewayResponse::error("ATTENDANCE_ERROR", &err, Some("zkteco-01".to_string()))),
        ),
    }
}
