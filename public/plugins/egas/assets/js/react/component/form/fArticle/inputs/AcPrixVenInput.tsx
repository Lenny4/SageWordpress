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
import { TOKEN } from "../../../../../token";
import { numberValidator } from "../../../../../functions/validator";

let translations: any = getTranslations();

export type AcPrixVenInputState = {
  defaultValue: number;
  acCategorie: number | string;
  acCoef: number | string;
  arPrixAch: number | string;
};

type FormState = {
  acPrixVen: InputInterface;
  realAcPrixVen: InputInterface;
  valueLock: InputInterface;
};

export const AcPrixVenInput = React.forwardRef(
  (
    { defaultValue, acCategorie, acCoef, arPrixAch }: AcPrixVenInputState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    acCoef = Number(acCoef);
    arPrixAch = Number(arPrixAch);
    const getExpectedAcPrixVen = () => {
      return Number((acCoef * arPrixAch).toFixed(2));
    };
    const getRealAcPrixVen = (v?: number | string) => {
      if (!v) {
        v = values.acPrixVen.value;
      }
      if (Number(v) === getExpectedAcPrixVen()) {
        return 0;
      }
      return v;
    };

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      const expectedAcPrixVen = getExpectedAcPrixVen();
      const acPrixVen = Number(v) === 0 ? expectedAcPrixVen : Number(v);
      return {
        acPrixVen: { value: acPrixVen },
        realAcPrixVen: { value: v.toString() },
        valueLock: { value: acPrixVen > 0 && acPrixVen !== expectedAcPrixVen },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        if (prop === "acPrixVen") {
          let newValue = Number(event.target.value);
          setValues((v) => {
            return {
              ...v,
              valueLock: {
                ...v.valueLock,
                value: newValue > 0 && newValue !== v.realAcPrixVen.value,
              },
            };
          });
        }
        handleChangeInputGeneric(event, prop, setValues);
      };

    const resetAcPrixVen = () => {
      setValues((v) => {
        return {
          ...v,
          acPrixVen: {
            ...v.acPrixVen,
            value: getExpectedAcPrixVen(),
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
    };

    const isValid = async () => {
      const error = await numberValidator({
        value: values.acPrixVen.value,
        canBeEmpty: true,
      });
      setValues((v) => {
        return {
          ...v,
          acPrixVen: {
            ...v.acPrixVen,
            error: error,
          },
        };
      });
      return error === "";
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
      isValid().finally(() => {
        setValues((v) => {
          return {
            ...v,
            realAcPrixVen: {
              ...v.realAcPrixVen,
              value: getRealAcPrixVen(v.acPrixVen.value),
            },
          };
        });
      });
    }, [values.acPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (!values.valueLock.value) {
        resetAcPrixVen();
      }
    }, [acCoef, arPrixAch]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={`_${TOKEN}_fArtclients[${acCategorie}][acPrixVen]`}>
          <Tooltip title={"acPrixVen"} arrow placement="top">
            <span>{translations["fArticles"]["acPrixVen"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={`_${TOKEN}_fArtclients[${acCategorie}][acPrixVen]`}
              name={`_${TOKEN}_fArtclients[${acCategorie}][acPrixVen]`}
              type={"hidden"}
              value={values.realAcPrixVen.value}
            />
            <input
              type={"number"}
              value={values.acPrixVen.value}
              onChange={handleChange("acPrixVen")}
              style={{ width: "100%" }}
              ref={inputRef}
              onBlur={() => {
                if (Number(values.acPrixVen.value) === 0) {
                  resetAcPrixVen();
                }
              }}
            />
            {values.acPrixVen.error && (
              <div className={`${TOKEN}_error_field`}>
                {values.acPrixVen.error}
              </div>
            )}
          </div>
          {Number(values.acPrixVen.value) !== getExpectedAcPrixVen() &&
            Number(values.acPrixVen.value) > 0 && (
              <div style={{ position: "relative", top: "-2px" }}>
                <Tooltip title={translations.sentences.acPrixVenInput} arrow>
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
