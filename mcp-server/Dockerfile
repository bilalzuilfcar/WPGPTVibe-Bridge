FROM node:24-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY tsconfig.json ./
COPY src ./src
RUN npm run check && npm run build

FROM node:24-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
COPY package*.json ./
RUN npm install --omit=dev
COPY --from=build /app/dist ./dist
RUN addgroup -S wpgptvibe && adduser -S -G wpgptvibe wpgptvibe \
    && mkdir -p /app/data && chown -R wpgptvibe:wpgptvibe /app
USER wpgptvibe
EXPOSE 8787
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD wget -qO- http://127.0.0.1:8787/health >/dev/null || exit 1
CMD ["node","dist/index.js"]
