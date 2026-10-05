services:
  instance:
    image: agenthub/runtime-{{TYPE}}:latest
    container_name: agenthub-{{TYPE}}-{{SLUG}}
    restart: unless-stopped
    ports:
      - "{{PORT}}:{{PORT}}"
    environment:
      - INSTANCE_NAME={{NAME}}
      - INSTANCE_SLUG={{SLUG}}
      - PORT={{PORT}}
      - GATEWAY_URL={{GATEWAY_URL}}
    volumes:
      - /opt/agenthub/instances/{{TYPE}}/{{SLUG}}:/data
    networks:
      - agenthub-net

networks:
  agenthub-net:
    external: true
