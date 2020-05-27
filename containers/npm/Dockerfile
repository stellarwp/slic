FROM tarampampam/node:12.17-alpine

RUN apk update && apk add curl && rm -rf /var/cache/apk/*

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN USER=node && \
    GROUP=node && \
    curl -SsL https://github.com/boxboat/fixuid/releases/download/v0.4/fixuid-0.4-linux-amd64.tar.gz | tar -C /usr/local/bin -xzf - && \
    chown root:root /usr/local/bin/fixuid && \
    chmod 4755 /usr/local/bin/fixuid && \
    mkdir -p /etc/fixuid && \
    printf "user: $USER\ngroup: $GROUP\npaths: [ '/project/node_modules', '/project/package.json', '/project/package.lock', '/home/node' ]" > /etc/fixuid/config.yml && \
    chmod a+x /usr/local/bin/docker-entrypoint.sh && \
    mkdir -p /home/node/

WORKDIR /project

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["--version"]

USER node
