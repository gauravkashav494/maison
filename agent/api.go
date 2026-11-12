package main

import (
	"bytes"
	"crypto/tls"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// API talks to EcomVision's /api/sync/v1.
type API struct {
	BaseURL string
	Token   string
	client  *http.Client
}

func NewAPI(baseURL, token string) *API {
	transport := &http.Transport{
		Proxy:           http.ProxyFromEnvironment,
		TLSClientConfig: &tls.Config{MinVersion: tls.VersionTLS12},
	}
	return &API{BaseURL: baseURL, Token: token, client: &http.Client{Timeout: 15 * time.Minute, Transport: transport}}
}

func (a *API) open(method, path string, body any) (*http.Response, error) {
	var reader io.Reader
	if body != nil {
		encoded, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		reader = bytes.NewReader(encoded)
	}

	request, err := http.NewRequest(method, a.BaseURL+"/"+path, reader)
	if err != nil {
		return nil, err
	}
	request.Header.Set("Authorization", "Bearer "+a.Token)
	request.Header.Set("Accept", "application/json")
	if body != nil {
		request.Header.Set("Content-Type", "application/json")
	}

	response, err := a.client.Do(request)
	if err != nil {
		return nil, err
	}
	if response.StatusCode >= 300 {
		defer response.Body.Close()
		detail, _ := io.ReadAll(io.LimitReader(response.Body, 300))
		return nil, fmt.Errorf("%s %s: HTTP %d: %s", method, path, response.StatusCode, detail)
	}
	return response, nil
}

// Call sends a request and decodes the JSON answer into out.
func (a *API) Call(method, path string, body, out any) error {
	response, err := a.open(method, path, body)
	if err != nil {
		return err
	}
	defer response.Body.Close()

	if out == nil {
		_, err = io.Copy(io.Discard, response.Body)
		return err
	}
	return json.NewDecoder(response.Body).Decode(out)
}
