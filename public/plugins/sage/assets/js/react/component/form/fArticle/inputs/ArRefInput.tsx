import * as React from "react";
import { useImperativeHandle } from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import { InputInterface } from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { stringValidator } from "../../../../../functions/validator";

const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
const wpnonce = $("[data-sage-nonce]").attr("data-sage-nonce");
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
      setLoading(true);
      const response = await fetch(
        siteUrl +
          "/index.php?rest_route=" +
          encodeURI("/sage/v1/farticle/" + values.arRef.value + "/available") +
          "&_wpnonce=" +
          wpnonce,
      );
      if (response.ok) {
        if (currentArRef !== values.arRef.value) {
          return;
        }
        let data: any = await response.json();
        setAvailableArRef(data.availableArRef);
      } else {
        // todo toast r
      }
      setLoading(false);
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        // todo
        return false;
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
        <label htmlFor={"_sage_arRef"}>
          <Tooltip title={"arRef"} arrow placement="top">
            <span>{translations["fArticles"]["arRef"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={"_sage_arRef"}
              name={"_sage_arRef"}
              type={"hidden"}
              value={availableArRef}
            />
            <input
              type={"text"}
              value={values.arRef.value}
              readOnly={!isNew}
              onChange={handleChange("arRef")}
              style={{ width: "100%" }}
            />
            {values.arRef.error && (
              <div className="sage_error_field">{values.arRef.error}</div>
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
