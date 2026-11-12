//go:build !windows

package main

import "errors"

func openStore(connection string) (Store, error) {
	return nil, errors.New("the agent's database connection only works on Windows")
}

func platformMain(args []string) error {
	return errors.New("the agent only installs on Windows")
}
