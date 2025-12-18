import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { stringValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);
let translations: any = getTranslations();

export type ArRefInputState = {
  isNew: boolean;
  defaultValue: string;
};

type FormState = {
  arRef: InputInterface;
};

let currentArRef = "";

export const ArRefInput = React.forwardRef(
  ({ isNew, defaultValue }: ArRefInputState, ref) => {
    const inputRef = useRef<any>(null);
    const getDefaultValue = (): FormState => {
      return {
        arRef: { value: defaultValue ?? "" },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());
    const [loading, setLoading] = React.useState<boolean>(false);
    const [availableArRef, setAvailableArRef] = React.useState<string>(
      isNew ? "" : values.arRef.value,
    );

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        handleChangeInputGeneric(event, prop, setValues);
      };

    const searchValue = async () => {
      if (!isNew) {
        return;
      }
      setAvailableArRef("");
      if (!(await _isValid())) {
        return;
      }
      setLoading(true);
      const response = await fetch(
        siteUrl +
          "/index.php?rest_route=" +
          encodeURIComponent(
            `/${TOKEN}/v1/farticles/` + values.arRef.value + "/available",
          ) +
          "&_wpnonce=" +
          wpnonce,
      );
      setLoading(false);
      if (response.ok) {
        if (currentArRef !== values.arRef.value) {
          return;
        }
        let data: any = await response.json();
        setAvailableArRef(data.availableArRef);
      } else {
        setValues((v) => {
          return {
            ...v,
            arRef: {
              ...v.arRef,
              error: translations.sentences.availableArRefError,
            },
          };
        });
      }
    };

    const _isValid = async () => {
      const errorArRef = await stringValidator({
        value: values.arRef.value,
        maxLength: 17,
        canBeEmpty: true,
        canHaveSpace: false,
      });
      if (errorArRef !== "") {
        setValues((v) => {
          return {
            ...v,
            arRef: {
              ...v.arRef,
              error: errorArRef,
            },
          };
        });
        return;
      }
      return errorArRef === "";
    };

    const isValid = async () => {
      let v = await _isValid();
      if (v && availableArRef === "") {
        v = false;
        setValues((v) => {
          return {
            ...v,
            arRef: {
              ...v.arRef,
              error: translations.sentences.availableArRefError,
            },
          };
        });
      }
      return v;
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValid();
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    React.useEffect(() => {
      currentArRef = values.arRef.value;
      const timeoutTyping = setTimeout(() => {
        searchValue();
      }, 500);
      return () => clearTimeout(timeoutTyping);
    }, [values.arRef.value]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={`_${TOKEN}_arRef`}>
          <Tooltip title={"arRef"} arrow placement="top">
            <span>{translations["fArticles"]["arRef"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={`_${TOKEN}_arRef`}
              name={`_${TOKEN}_arRef`}
              type={"hidden"}
              value={availableArRef}
            />
            <input
              type={"text"}
              value={values.arRef.value}
              readOnly={!isNew}
              onChange={handleChange("arRef")}
              style={{ width: "100%" }}
              ref={inputRef}
            />
            {values.arRef.error && (
              <div className={`${TOKEN}_error_field`}>{values.arRef.error}</div>
            )}
            {isNew && (
              <>
                {loading ? (
                  <svg
                    className="svg-spinner"
                    viewBox="0 0 50 50"
                    style={{ right: 0 }}
                  >
                    <circle
                      className="path"
                      cx="25"
                      cy="25"
                      r="20"
                      fill="none"
                      stroke-width="5"
                    ></circle>
                  </svg>
                ) : (
                  <>
                    <span
                      className={
                        "dashicons dashicons-" +
                        (availableArRef !== "" ? "yes" : "no") +
                        " endDashiconsInput"
                      }
                      style={{
                        color: availableArRef !== "" ? "green" : "red",
                        right: 0,
                        top: 7,
                      }}
                    ></span>
                  </>
                )}
              </>
            )}
          </div>
          {isNew && availableArRef !== "" && (
            <div
              style={{ marginLeft: 5, display: "flex", alignItems: "center" }}
            >
              <span className="h5">{availableArRef}</span>
              <Tooltip title={translations.sentences.availableArRef} arrow>
                <IconButton>
                  <InfoIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            </div>
          )}
        </div>
      </>
    );
  },
);
