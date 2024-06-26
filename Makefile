
SHELL := /bin/bash

ifneq (,$(wildcard ./.env))
    include .env
    export
endif

.PHONY: docker
docker:
	docker build -f Dockerfile --tag ${REPOSITORY}/${IMAGENAME}:${TAG} .
	docker build -f db_dockerfile --tag ${REPOSITORY}/${IMAGENAME}_db:${TAG} .

.PHONY: push
push:
	docker push ${REPOSITORY}/${IMAGENAME}:${TAG}
	docker push ${REPOSITORY}/${IMAGENAME}_db:${TAG}

.PHONY: build-push
build-push: 
	docker build -f Dockerfile --tag ${REPOSITORY}/${IMAGENAME}:${TAG} . 
	docker build -f db_dockerfile --tag ${REPOSITORY}/${IMAGENAME}_db:${TAG} .
	docker push ${REPOSITORY}/${IMAGENAME}:${TAG}
	docker push ${REPOSITORY}/${IMAGENAME}_db:${TAG}
