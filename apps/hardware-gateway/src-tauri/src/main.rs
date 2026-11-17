mod adapters;
mod api;
mod core;

use crate::core::hub::DeviceHub;
use std::net::SocketAddr;
use tracing_subscriber::{layer::SubscriberExt, util::SubscriberInitExt};

#[tokio::main]
async fn main() {
    // راه‌اندازی سیستم لاگ‌گیری ساختاریافته
    tracing_subscriber::registry()
        .with(tracing_subscriber::EnvFilter::try_from_default_env().unwrap_or_else(|_| "info".into()))
        .with(tracing_subscriber::fmt::layer())
        .init();

    tracing::info!("Webino Hardware Gateway daemon starting...");

    let hub = DeviceHub::new();
    let app = api::server::build_router(hub);

    let addr = SocketAddr::from(([127, 0, 0, 1], 9100));
    tracing::info!("REST & WebSocket Gateway listening on http://{}", addr);

    let listener = tokio::net::TcpListener::bind(addr).await.unwrap();
    axum::serve(listener, app).await.unwrap();
}
