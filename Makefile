#!/usr/bin/make

SHELL=/bin/bash
CWD:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

VERSION ?= dev

zip-content:
	cd ".."; zip -r acc_user_importer/acc_user_importer-$(VERSION).zip acc_user_importer -x "acc_user_importer/.git/*"
