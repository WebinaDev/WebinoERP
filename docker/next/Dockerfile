# Build context: Plugins/Webina workspace root (includes packages/webina-ui)
FROM node:22-alpine AS deps
WORKDIR /src
COPY packages/webina-ui ./packages/webina-ui
COPY WebinoERP/frontend/package.json WebinoERP/frontend/package-lock.json ./WebinoERP/frontend/
WORKDIR /src/packages/webina-ui
RUN npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
WORKDIR /src/WebinoERP/frontend
RUN npm ci --no-audit --no-fund

FROM node:22-alpine AS builder
WORKDIR /src
COPY packages/webina-ui ./packages/webina-ui
COPY WebinoERP/frontend ./WebinoERP/frontend
COPY --from=deps /src/WebinoERP/frontend/node_modules ./WebinoERP/frontend/node_modules
COPY --from=deps /src/packages/webina-ui/node_modules ./packages/webina-ui/node_modules
WORKDIR /src/WebinoERP/frontend
ENV NEXT_TELEMETRY_DISABLED=1
ARG API_PROXY_TARGET=http://backend:8080
ENV API_PROXY_TARGET=${API_PROXY_TARGET}
ARG INTERNAL_API_URL=http://backend:8080
ENV INTERNAL_API_URL=${INTERNAL_API_URL}
ARG NEXT_PUBLIC_API_URL=/api
ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}
RUN npm run build

FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ARG INTERNAL_API_URL=http://backend:8080
ENV INTERNAL_API_URL=${INTERNAL_API_URL}
RUN apk add --no-cache wget \
    && addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs
COPY --from=builder /src/WebinoERP/frontend/public ./public
COPY --from=builder --chown=nextjs:nodejs /src/WebinoERP/frontend/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /src/WebinoERP/frontend/.next/static ./.next/static
USER nextjs
EXPOSE 3000
ENV PORT=3000
ENV HOSTNAME=0.0.0.0
CMD ["node", "server.js"]
