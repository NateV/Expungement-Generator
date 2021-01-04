

.PHONY build-push:
build-push:
	docker build . -t natev/eg-docker-frontend:latest
	docker push natev/eg-docker-frontend:latest