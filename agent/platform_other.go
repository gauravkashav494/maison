//go:build !windows

package main

import "errors"

func platformMain(args []string) error {
	return errors.New("the agent only installs on Windows")
}
