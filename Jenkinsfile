#!/usr/bin/env groovy

node {

    try {

       stage 'Clean workspace'

           deleteDir()

       stage 'Checkout'

            checkout scm

       stage 'Run tests in Docker (PHP 5.6)'

            env.DOCKER_IMAGE = "php5.6"
            env.DOCKER_FILES_INPUT = "."
            env.DOCKER_FILES_OUTPUT = "README.md"
            env.BUILD_COMMAND = "./.ci.sh"

            timeout(20) {
                wrap([$class: 'AnsiColorBuildWrapper', 'colorMapName': 'XTerm']) {
                  sh '/docker/images/1-prepare.sh'
                }
            }

       stage 'Run tests in Docker (PHP 7)'

            env.DOCKER_IMAGE = "php7.0"
            env.DOCKER_FILES_INPUT = "."
            env.DOCKER_FILES_OUTPUT = "phpunit/"
            env.BUILD_COMMAND = "./.ci.sh"

            timeout(20) {
                wrap([$class: 'AnsiColorBuildWrapper', 'colorMapName': 'XTerm']) {
                  sh '/docker/images/1-prepare.sh'
                }
            }

            currentBuild.result = "SUCCESS"
    }

    catch (err) {

        currentBuild.result = "FAILURE"

        throw err
    }
}
