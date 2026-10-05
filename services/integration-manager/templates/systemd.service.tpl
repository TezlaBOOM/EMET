[Unit]
Description=AgentHub Instance Service for {{NAME}} ({{SLUG}})
After=network.target agenthub.target
PartOf=agenthub.target

[Service]
Type=simple
User=agenthub-runner
Group=agenthub-runner
WorkingDirectory=/opt/agenthub/instances/{{TYPE}}/{{SLUG}}
EnvironmentFile=-/opt/agenthub/instances/{{TYPE}}/{{SLUG}}/.env
ExecStart={{START_COMMAND}}
Restart=on-failure
RestartSec=5s
LimitNOFILE=65536
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
