

.PHONY: push-frontend
push-frontend:
# command to build the php frontend image and push it to dockerhub.
	sudo docker build . -t natev/eg-docker-frontend:latest
	sudo docker push natev/eg-docker-frontend:latest