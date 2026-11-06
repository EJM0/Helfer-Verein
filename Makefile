# Builds an installable archive in build/helfer.tar.gz
app_name=helfer
build_dir=$(CURDIR)/build
stage_dir=$(build_dir)/stage

.PHONY: build appstore clean

build:
	npm ci
	npm run build

appstore: build
	rm -rf $(stage_dir)
	mkdir -p $(stage_dir)/$(app_name)
	cp -r appinfo css img js lib templates LICENSE README.md CHANGELOG.md $(stage_dir)/$(app_name)/
	rm -f $(stage_dir)/$(app_name)/js/*.map
	tar -czf $(build_dir)/$(app_name).tar.gz -C $(stage_dir) $(app_name)
	@echo "Archive: $(build_dir)/$(app_name).tar.gz"

clean:
	rm -rf $(build_dir)
